<?php

namespace App\Domain\Reservations\Actions;

use App\Domain\Exceptions\InvalidReservationState;
use App\Domain\Exceptions\RoomAlreadyOccupied;
use App\Domain\Reservations\Support\ReservationStatusWriter;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CheckInReservation
{
    public function __construct(
        private readonly ReservationStatusWriter $statuses,
    ) {
    }

    public function execute(Reservation $reservation, ?User $actor = null, ?string $note = null): Reservation
    {
        return DB::transaction(function () use ($reservation, $actor, $note): Reservation {
            $reservation = Reservation::query()->lockForUpdate()->findOrFail($reservation->id);

            if ($reservation->status !== Reservation::STATUS_CONFIRMED) {
                throw InvalidReservationState::cannotTransition(
                    $reservation,
                    Reservation::STATUS_CHECKED_IN,
                    'only confirmed reservations can be checked in',
                );
            }

            $room = Room::query()->lockForUpdate()->findOrFail($reservation->room_id);

            if ($room->status === Room::STATUS_OCCUPIED) {
                throw RoomAlreadyOccupied::for($room);
            }
            if ($room->status === Room::STATUS_MAINTENANCE) {
                throw RoomAlreadyOccupied::for($room);
            }

            $from = $reservation->status;

            $reservation->fill([
                'status'        => Reservation::STATUS_CHECKED_IN,
                'checked_in_at' => now(),
                'updated_by'    => $actor?->id,
            ])->save();

            $room->fill(['status' => Room::STATUS_OCCUPIED])->save();

            $this->statuses->record(
                $reservation,
                $from,
                Reservation::STATUS_CHECKED_IN,
                $actor?->id,
                $note,
            );

            return $reservation->refresh();
        });
    }
}
