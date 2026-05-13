<?php

namespace App\Domain\Reservations\Actions;

use App\Domain\Reservations\Support\ReservationTotals;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class RecordPayment
{
    public function __construct(
        private readonly ReservationTotals $totals,
    ) {
    }

    public function execute(
        Reservation $reservation,
        string $method,
        float $amount,
        ?User $actor = null,
        ?string $reference = null,
        ?string $note = null,
        ?string $status = null,
    ): Payment {
        if (! in_array($method, Payment::METHODS, true)) {
            throw new InvalidArgumentException("Unsupported payment method: {$method}");
        }

        if ($amount === 0.0) {
            throw new InvalidArgumentException('Payment amount must be non-zero.');
        }

        $status ??= Payment::STATUS_COMPLETED;

        return DB::transaction(function () use ($reservation, $method, $amount, $actor, $reference, $note, $status): Payment {
            // Lock the reservation row so concurrent payments serialize.
            $reservation = Reservation::query()->lockForUpdate()->findOrFail($reservation->id);

            $payment = Payment::create([
                'property_id'    => $reservation->property_id,
                'reservation_id' => $reservation->id,
                'method'         => $method,
                'amount'         => round($amount, 2),
                'currency'       => $reservation->currency,
                'status'         => $status,
                'reference'      => $reference,
                'note'           => $note,
                'paid_at'        => now(),
                'received_by'    => $actor?->id,
            ]);

            // Mark refund-class payments separately so the recompute
            // doesn't accidentally re-derive `paid` from a -refund.
            if ($status === Payment::STATUS_REFUNDED && $amount > 0) {
                $reservation->fill(['payment_status' => Reservation::PAYMENT_REFUNDED])->save();
            }

            $this->totals->recompute($reservation);

            return $payment;
        });
    }
}
