<?php

declare(strict_types=1);

namespace App\Modules\Payment\Actions;

use App\Modules\Payment\Models\Payment;
use App\Modules\Payment\Services\MoneyAuditLogger;
use App\Modules\Riding\Models\Ride;
use App\Modules\Wallet\Services\WalletPoster;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Charges the customer-side cancellation fee per the policy matrix
 * in `docs/phase-2.2/cancellation-refund-rules.md`.
 *
 * Fee amounts:
 *   0 GEL  → no-op, never invoked.
 *   2 GEL  → customer cancelled while driver en route after 2 min.
 *   5 GEL  → customer cancelled after driver arrived.
 *   5 GEL  → driver no-show (paid TO the driver, not from the customer).
 *
 * The fee is settled as a `cash` payment row attached to the ride —
 * we never round-trip the gateway because (a) we may not have a card
 * on file, and (b) the customer "owes" us; we apply the fee to their
 * wallet credit balance first, then to the next ride's bill.
 */
final class IssueCancellationFee
{
    public function __construct(
        private readonly WalletPoster $wallet,
        private readonly MoneyAuditLogger $audit,
    ) {}

    public function execute(Ride $ride, Money $fee, string $reason): Payment
    {
        Log::channel('payment')->info('IssueCancellationFee', [
            'ride_ulid' => $ride->ulid,
            'fee' => $fee->toDecimal(),
            'currency' => $fee->currency,
            'reason' => $reason,
        ]);

        return DB::transaction(function () use ($ride, $fee, $reason): Payment {
            $payment = Payment::create([
                'ride_id' => $ride->id,
                'customer_id' => $ride->customer_id,
                'provider' => 'cash',
                'provider_intent_id' => 'cash:cancel:'.$ride->ulid,
                'method' => 'cash',
                'amount' => $fee->toDecimal(),
                'currency' => $fee->currency,
                'status' => 'captured',
                'captured_at' => now(),
                'raw_response' => ['reason' => $reason, 'kind' => 'cancellation_fee'],
            ]);

            // Mirror onto the ride row.
            $ride->final_amount = $fee->toDecimal();
            $ride->payment_id = $payment->id;
            $ride->save();

            // Debit the customer wallet (records the debt; will be
            // netted against any existing wallet credit). The customer
            // wallet is allowed to go negative for the cancellation-
            // fee path — the next ride collects the outstanding.
            $customer = $ride->customer()->first();
            if ($customer !== null) {
                $this->wallet->post(
                    user: $customer,
                    amount: $fee,
                    opts: [
                        'kind' => 'ride_charge',
                        'direction' => 'debit',
                        'ride_id' => $ride->id,
                        'payment_id' => $payment->id,
                        'description' => "Cancellation fee: {$reason}",
                        'meta' => [
                            'idempotency_key' => "cancel_fee:{$ride->ulid}",
                            'reason' => $reason,
                        ],
                    ],
                );
            }

            $this->audit->record(
                event: 'payment.cancellation_fee',
                subject: $payment,
                amountMinor: $fee->minor,
                currency: $fee->currency,
                meta: [
                    'ride_ulid' => $ride->ulid,
                    'reason' => $reason,
                ],
            );

            return $payment;
        });
    }
}
