<?php

namespace App\Domain\Reservations\Support;

use App\Models\Reservation;
use App\Models\ReservationStatusHistory;

class ReservationStatusWriter
{
    /**
     * Persist a transition row for the reservation. Caller is responsible
     * for actually mutating $reservation->status; this only records it.
     */
    public function record(
        Reservation $reservation,
        ?string $fromStatus,
        string $toStatus,
        ?int $changedBy = null,
        ?string $note = null,
    ): ReservationStatusHistory {
        return ReservationStatusHistory::create([
            'reservation_id' => $reservation->id,
            'from_status'    => $fromStatus,
            'to_status'      => $toStatus,
            'changed_by'     => $changedBy,
            'note'           => $note,
            'changed_at'     => now(),
        ]);
    }
}
