<?php

declare(strict_types=1);

namespace App\Modules\Payment\Actions;

use App\Modules\Identity\Models\User;
use App\Modules\Payment\Models\Payment;
use App\Modules\Payment\Models\Refund;
use App\Modules\Payment\Services\MoneyAuditLogger;
use App\Modules\Payment\Services\PaymentGatewayManager;
use App\Modules\Wallet\Services\WalletPoster;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

/**
 * Reverses (or partially reverses) a captured ride payment.
 *
 * Two flavours:
 *   - Card / wallet: gateway refund AND wallet credit to the customer.
 *   - Cash:          wallet credit only — driver collected real cash,
 *                    we owe the customer a credit they'll burn on the
 *                    next ride.
 *
 * The action:
 *   1. Validates the refund amount against `payment.amount - prior_refunds`.
 *   2. Calls the gateway's refund() (no-op for cash).
 *   3. Persists a `refunds` row with `status = succeeded` or `failed`.
 *   4. On success: credits the customer wallet via {@see WalletPoster}.
 *   5. On success: debits the driver wallet (clawback) for the portion
 *      they'd have earned on the refunded amount.
 *
 * Idempotent on `(payment_id, reason, amount, initiated_by_user_id,
 * occurred_at)` via the wallet poster's idempotency key.
 */
final class IssueRideRefund
{
    public function __construct(
        private readonly PaymentGatewayManager $gateways,
        private readonly WalletPoster $wallet,
        private readonly MoneyAuditLogger $audit,
    ) {}

    public function execute(Payment $payment, Money $amount, string $reason, User $initiatedBy): Refund
    {
        if ($payment->status !== 'captured' && $payment->status !== 'partially_refunded') {
            throw new InvalidArgumentException("Payment {$payment->id} cannot be refunded from state {$payment->status}");
        }
        if ($amount->minor <= 0) {
            throw new InvalidArgumentException('refund amount must be > 0');
        }
        if ($amount->currency !== $payment->currency) {
            throw new InvalidArgumentException("refund currency {$amount->currency} != payment currency {$payment->currency}");
        }

        $paid = (int) round((float) $payment->amount * 100);
        $alreadyRefunded = (int) round(
            (float) Refund::query()
                ->where('payment_id', $payment->id)
                ->where('status', 'succeeded')
                ->sum('amount') * 100
        );
        $remaining = $paid - $alreadyRefunded;
        if ($amount->minor > $remaining) {
            throw new InvalidArgumentException(
                "refund {$amount->toDecimal()} exceeds remaining {$remaining}/100 on payment {$payment->id}"
            );
        }

        $gatewayName = $payment->provider;
        $gateway = $this->gateways->driver($gatewayName);

        $result = $gateway->refund(
            providerIntentId: (string) $payment->provider_intent_id,
            amountMinor: $amount->minor,
        );

        return DB::transaction(function () use ($payment, $amount, $reason, $initiatedBy, $result, $gatewayName, $remaining): Refund {
            $refund = Refund::create([
                'payment_id' => $payment->id,
                'amount' => $amount->toDecimal(),
                'currency' => $amount->currency,
                'reason' => substr($reason, 0, 120),
                'initiated_by_user_id' => $initiatedBy->id,
                'status' => $result->ok ? 'succeeded' : 'failed',
                'provider_refund_id' => $result->providerIntentId,
            ]);

            if (! $result->ok) {
                Log::channel('payment')->warning('IssueRideRefund gateway failed', [
                    'payment_id' => $payment->id,
                    'failure_code' => $result->failureCode,
                ]);

                return $refund;
            }

            // Update the parent payment's status.
            $isFull = $amount->minor === $remaining;
            $payment->status = $isFull ? 'refunded' : 'partially_refunded';
            $payment->save();

            // Credit the customer's wallet.
            $customer = $payment->ride->customer;
            if ($customer !== null) {
                $this->wallet->post(
                    user: $customer,
                    amount: $amount,
                    opts: [
                        'kind' => 'refund',
                        'direction' => 'credit',
                        'ride_id' => $payment->ride_id,
                        'payment_id' => $payment->id,
                        'description' => "Refund: {$reason}",
                        'meta' => [
                            'idempotency_key' => "refund:{$refund->id}",
                            'gateway' => $gatewayName,
                            'initiated_by_user_id' => $initiatedBy->id,
                        ],
                    ],
                );
            }

            // Driver clawback. We don't have a per-line commission
            // split for the refund — pro-rate by the same ratio that
            // the original ride used.
            $driver = $payment->ride->driver;
            if ($driver !== null && $payment->ride->driver_earnings !== null) {
                $rideTotal = (int) round((float) $payment->ride->final_amount * 100);
                $driverShare = (int) round((float) $payment->ride->driver_earnings * 100);
                $clawbackMinor = $rideTotal > 0
                    ? (int) round($driverShare * ($amount->minor / $rideTotal))
                    : 0;

                if ($clawbackMinor > 0) {
                    $this->wallet->post(
                        user: $driver->user,
                        amount: new Money($clawbackMinor, $amount->currency),
                        opts: [
                            'kind' => 'adjustment',
                            'direction' => 'debit',
                            'ride_id' => $payment->ride_id,
                            'payment_id' => $payment->id,
                            'description' => "Driver clawback for refund {$refund->id}",
                            'meta' => [
                                'idempotency_key' => "clawback:{$refund->id}",
                                'gateway' => $gatewayName,
                            ],
                        ],
                    );
                }
            }

            $this->audit->record(
                event: 'payment.refunded',
                subject: $refund,
                amountMinor: $amount->minor,
                currency: $amount->currency,
                meta: [
                    'payment_id' => $payment->id,
                    'reason' => $reason,
                    'initiated_by_user_id' => $initiatedBy->id,
                    'gateway' => $gatewayName,
                    'partial' => ! $isFull,
                ],
            );

            return $refund;
        });
    }
}
