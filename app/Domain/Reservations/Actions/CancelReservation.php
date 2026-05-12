<?php

namespace App\Domain\Reservations\Actions;

use App\Domain\Availability\AvailabilityService;
use App\Domain\Exceptions\InvalidReservationState;
use App\Domain\Reservations\Support\ReservationStatusWriter;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CancelReservation
{
    public function __construct(
        private readonly AvailabilityService $availability,
        private readonly ReservationStatusWriter $statuses,
    ) {
    }

    public function execute(Reservation $reservation, string $reason, ?User $actor = null): Reservation
    {
        return DB::transaction(function () use ($reservation, $reason, $actor): Reservation {
            // Lock the row to serialize with concurrent state mutations.
            $reservation = Reservation::query()->lockForUpdate()->findOrFail($reservation->id);

            $disallowed = [
                Reservation::STATUS_CHECKED_OUT,
                Reservation::STATUS_CANCELLED,
            ];
            if (in_array($reservation->status, $disallowed, true)) {
                throw InvalidReservationState::cannotTransition(
                    $reservation,
                    Reservation::STATUS_CANCELLED,
                    'reservation is in a terminal state',
                );
            }

            $from = $reservation->status;

            $reservation->fill([
                'status'              => Reservation::STATUS_CANCELLED,
                'cancelled_at'        => now(),
                'cancellation_reason' => $reason,
                'updated_by'          => $actor?->id,
            ])->save();

            $this->availability->release($reservation);

            $this->statuses->record(
                $reservation,
                $from,
                Reservation::STATUS_CANCELLED,
                $actor?->id,
                $reason,
            );

            return $reservation->refresh();
        });
    }
}
