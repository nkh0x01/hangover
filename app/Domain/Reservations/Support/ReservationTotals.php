<?php

namespace App\Domain\Reservations\Support;

use App\Models\Reservation;
use App\Models\ReservationCharge;

class ReservationTotals
{
    /**
     * Recompute every aggregate field on the reservation from its nights,
     * charges, and payments. Saves the reservation. Idempotent.
     *
     *   room_rate_total = SUM(reservation_nights.nightly_rate)
     *   extras_total    = SUM(charges WHERE type IN (product, fee))
     *   taxes_total     = SUM(charges WHERE type = tax)
     *   discount_total  = SUM(charges WHERE type = discount)
     *   grand_total     = room_rate_total + extras_total + taxes_total - discount_total
     *   paid_total      = SUM(payments WHERE status = completed)
     *   payment_status  = derived from paid_total vs grand_total
     */
    public function recompute(Reservation $reservation): Reservation
    {
        $reservation->loadMissing(['nightsBreakdown', 'charges', 'payments']);

        $roomTotal = (float) $reservation->nightsBreakdown
            ->sum(fn ($n) => (float) $n->nightly_rate);

        $extras = (float) $reservation->charges
            ->whereIn('type', [ReservationCharge::TYPE_PRODUCT, ReservationCharge::TYPE_FEE])
            ->sum(fn ($c) => (float) $c->total);

        $taxes = (float) $reservation->charges
            ->where('type', ReservationCharge::TYPE_TAX)
            ->sum(fn ($c) => (float) $c->total);

        $discount = (float) $reservation->charges
            ->where('type', ReservationCharge::TYPE_DISCOUNT)
            ->sum(fn ($c) => (float) $c->total);

        $grand = round($roomTotal + $extras + $taxes - $discount, 2);

        $paid = (float) $reservation->payments
            ->where('status', \App\Models\Payment::STATUS_COMPLETED)
            ->sum(fn ($p) => (float) $p->amount);

        $reservation->fill([
            'room_rate_total' => round($roomTotal, 2),
            'extras_total'    => round($extras, 2),
            'taxes_total'     => round($taxes, 2),
            'discount_total'  => round($discount, 2),
            'grand_total'     => $grand,
            'paid_total'      => round($paid, 2),
            'payment_status'  => $this->derivePaymentStatus($paid, $grand, $reservation->payment_status),
        ]);

        $reservation->save();

        return $reservation;
    }

    private function derivePaymentStatus(float $paid, float $grand, string $current): string
    {
        // Preserve special states that totals can't infer.
        if (in_array($current, [
            Reservation::PAYMENT_REFUNDED,
            Reservation::PAYMENT_PLATFORM_PAID,
        ], true)) {
            return $current;
        }

        $epsilon = 0.005;

        if ($paid <= $epsilon) {
            return Reservation::PAYMENT_UNPAID;
        }
        if ($paid + $epsilon < $grand) {
            return Reservation::PAYMENT_PARTIAL;
        }

        return Reservation::PAYMENT_PAID;
    }
}
