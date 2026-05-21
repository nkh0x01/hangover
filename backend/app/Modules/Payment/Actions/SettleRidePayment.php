<?php

declare(strict_types=1);

namespace App\Modules\Payment\Actions;

use App\Modules\Payment\Models\Payment;
use App\Modules\Payment\Services\MoneyAuditLogger;
use App\Modules\Payment\Services\PaymentGatewayManager;
use App\Modules\Pricing\Services\CommissionCalculator;
use App\Modules\Riding\Models\Ride;
use App\Modules\Wallet\Services\WalletPoster;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * "Money clearing" action invoked by {@see \App\Modules\Riding\Actions\CompleteTrip}.
 *
 * Sequence:
 *   1. Resolve the gateway for the ride's payment method.
 *   2. authorize+capture against the gateway (cash → instant ok;
 *      card → real call once wired).
 *   3. Persist the `payments` row + link to the ride.
 *   4. Compute commission via {@see CommissionCalculator}.
 *   5. Atomic wallet posting (driver: +earnings; platform commission
 *      logged as a transaction on the driver wallet with `kind =
 *      adjustment`).
 *
 * Failure modes:
 *   - Gateway returns `ok = false` → payment row saved with
 *     `status = failed` and `failure_code` set; the ride is allowed
 *     to remain `completed` (the driver still drove). Ops resolves
 *     via the finance panel.
 *   - Wallet posting throws → DB transaction rolls back EVERYTHING
 *     including the payment row; SettleRidePayment re-raises so the
 *     job retries on the queue.
 *
 * Idempotency: keyed off `ride.ulid`. Re-running this action against
 * a ride that already has a captured payment short-circuits and
 * returns the existing payment row.
 */
final class SettleRidePayment
{
    public function __construct(
        private readonly PaymentGatewayManager $gateways,
        private readonly CommissionCalculator $commission,
        private readonly WalletPoster $wallet,
        private readonly MoneyAuditLogger $audit,
    ) {}

    public function execute(Ride $ride): Payment
    {
        $existing = Payment::query()
            ->where('ride_id', $ride->id)
            ->whereIn('status', ['captured', 'refunded', 'partially_refunded'])
            ->first();
        if ($existing !== null) {
            Log::channel('payment')->info('SettleRidePayment idempotent skip', [
                'ride_ulid' => $ride->ulid,
                'payment_id' => $existing->id,
                'status' => $existing->status,
            ]);

            return $existing;
        }

        $method = (string) $ride->payment_method;
        $gateway = $this->gateways->forMethod($method);

        $amount = Money::fromDecimal((float) ($ride->final_amount ?? $ride->quoted_amount), (string) $ride->currency);

        $gatewayName = $this->gateways->gatewayNameForMethod($method);
        $result = $gateway->authorize(
            amountMinor: $amount->minor,
            currency: $amount->currency,
            methodToken: '',
            rideUlid: $ride->ulid,
        );

        // Cash gateways report `captured` directly. For
        // authorize-then-capture flows (card), capture is the second
        // hop — but for the pilot path we only ever exercise the
        // synchronous one-shot.
        if ($result->ok && $result->status === 'authorized') {
            $result = $gateway->capture($result->providerIntentId ?? '');
        }

        return DB::transaction(function () use ($ride, $amount, $gatewayName, $result, $method): Payment {
            $payment = Payment::create([
                'ride_id' => $ride->id,
                'customer_id' => $ride->customer_id,
                'provider' => $this->providerForMethod($method, $gatewayName),
                'provider_intent_id' => $result->providerIntentId,
                'method' => $method,
                'amount' => $amount->toDecimal(),
                'currency' => $amount->currency,
                'status' => $result->ok ? ($result->status === 'captured' ? 'captured' : $result->status) : 'failed',
                'failure_code' => $result->ok ? null : $result->failureCode,
                'captured_at' => ($result->ok && $result->status === 'captured') ? now() : null,
                'raw_response' => $result->raw,
            ]);

            // Link back to the ride.
            $ride->payment_id = $payment->id;
            $ride->save();

            if (! $result->ok) {
                Log::channel('payment')->warning('SettleRidePayment failed', [
                    'ride_ulid' => $ride->ulid,
                    'gateway' => $gatewayName,
                    'failure_code' => $result->failureCode,
                ]);

                return $payment;
            }

            // Commission split + driver wallet posting. Skip if the
            // ride has no driver (defensive — should never happen at
            // this stage).
            $driver = $ride->driver()->first();
            if ($driver === null) {
                Log::channel('payment')->error('SettleRidePayment: ride has no driver at settlement', [
                    'ride_id' => $ride->id,
                ]);

                return $payment;
            }

            $split = $this->commission->split($amount, $driver, $ride->city()->first());

            // Driver earns the full fare minus commission. We post as
            // two transactions to make the audit trail explicit.
            $this->wallet->post(
                user: $driver->user,
                amount: $amount,
                opts: [
                    'kind' => 'ride_payout',
                    'direction' => 'credit',
                    'ride_id' => $ride->id,
                    'payment_id' => $payment->id,
                    'description' => "Ride payout {$ride->ulid}",
                    'meta' => [
                        'idempotency_key' => "ride_payout:{$ride->ulid}",
                        'gateway' => $gatewayName,
                    ],
                ],
            );

            $this->wallet->post(
                user: $driver->user,
                amount: $split['commission'],
                opts: [
                    'kind' => 'adjustment',
                    'direction' => 'debit',
                    'ride_id' => $ride->id,
                    'payment_id' => $payment->id,
                    'description' => sprintf('Platform commission (%.1f%%) for ride %s', $split['rate'] * 100, $ride->ulid),
                    'meta' => [
                        'idempotency_key' => "commission:{$ride->ulid}",
                        'rate' => $split['rate'],
                    ],
                ],
            );

            // Mirror the split onto the ride row so the finance UI doesn't
            // need to JOIN transactions to show commission per ride.
            $ride->commission_amount = $split['commission']->toDecimal();
            $ride->driver_earnings = $split['driverEarnings']->toDecimal();
            $ride->save();

            $this->audit->record(
                event: 'payment.captured',
                subject: $payment,
                amountMinor: $amount->minor,
                currency: $amount->currency,
                meta: [
                    'ride_ulid' => $ride->ulid,
                    'gateway' => $gatewayName,
                    'commission_minor' => $split['commission']->minor,
                    'driver_earnings_minor' => $split['driverEarnings']->minor,
                    'rate' => $split['rate'],
                ],
            );

            return $payment;
        });
    }

    private function providerForMethod(string $method, string $gatewayName): string
    {
        // The `payments.provider` enum is the gateway name for card,
        // and the method itself for cash/wallet.
        return match ($method) {
            'cash' => 'cash',
            'wallet' => 'wallet',
            default => $gatewayName,
        };
    }
}
