<?php

namespace App\Domain\Reservations\Actions;

use App\Domain\Availability\AvailabilityService;
use App\Domain\Availability\Period;
use App\Domain\Exceptions\InvalidReservationState;
use App\Domain\Pricing\PricingService;
use App\Domain\Reservations\Support\ReservationStatusWriter;
use App\Domain\Reservations\Support\ReservationTotals;
use App\Models\Reservation;
use App\Models\ReservationNight;
use App\Models\Room;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Move a reservation to a new date range and/or a new room. The old
 * availability rows are released and the new ones are reserved inside
 * a single transaction so the room can never be left partially-booked.
 */
class MoveReservation
{
    public function __construct(
        private readonly AvailabilityService $availability,
        private readonly PricingService $pricing,
        private readonly ReservationStatusWriter $statuses,
        private readonly ReservationTotals $totals,
    ) {
    }

    public function execute(
        Reservation $reservation,
        ?Period $newPeriod = null,
        ?Room $newRoom = null,
        ?User $actor = null,
        ?string $note = null,
    ): Reservation {
        if (! $newPeriod && ! $newRoom) {
            return $reservation;
        }

        return DB::transaction(function () use ($reservation, $newPeriod, $newRoom, $actor, $note): Reservation {
            $reservation = Reservation::query()->lockForUpdate()->findOrFail($reservation->id);

            $movable = [
                Reservation::STATUS_PENDING,
                Reservation::STATUS_CONFIRMED,
            ];
            if (! in_array($reservation->status, $movable, true)) {
                throw InvalidReservationState::cannotTransition(
                    $reservation,
                    'moved',
                    'only pending or confirmed reservations can be moved',
                );
            }

            $period = $newPeriod ?? new Period(
                $reservation->check_in_date,
                $reservation->check_out_date,
            );

            $room = $newRoom ?? Room::query()->findOrFail($reservation->room_id);

            // Restriction check on the NEW period before we release the old hold.
            $restrictions = $this->pricing->restrictionsForStay($room->roomType, $period, $room);
            if ($violation = $restrictions->violatedBy($period)) {
                throw \App\Domain\Exceptions\StayRestrictionViolated::from($violation);
            }

            // Step 1: release the current hold.
            $this->availability->release($reservation);

            // Step 2: take the new hold. Throws on conflict; the
            // surrounding transaction rolls the release back.
            $this->availability->reserve($reservation, $room, $period);

            // Step 3: re-snapshot nights at current pricing.
            $reservation->nightsBreakdown()->delete();
            $quote = $this->pricing->priceForStay($room->roomType, $period);
            $rows = [];
            foreach ($quote->nights as $nightly) {
                $rows[] = [
                    'reservation_id' => $reservation->id,
                    'date'           => $nightly->date->toDateString(),
                    'room_id'        => $room->id,
                    'nightly_rate'   => $nightly->amount,
                    'currency'       => $nightly->currency,
                    'created_at'     => now(),
                    'updated_at'     => now(),
                ];
            }
            ReservationNight::query()->insert($rows);

            $reservation->fill([
                'room_id'        => $room->id,
                'room_type_id'   => $room->room_type_id,
                'check_in_date'  => $period->checkIn->toDateString(),
                'check_out_date' => $period->checkOut->toDateString(),
                'nights'         => $period->nightCount(),
                'currency'       => $quote->currency,
                'updated_by'     => $actor?->id,
            ])->save();

            $this->totals->recompute($reservation);

            $this->statuses->record(
                $reservation,
                $reservation->status,
                $reservation->status,
                $actor?->id,
                $note ?? sprintf(
                    'Reservation moved to room #%d for %s',
                    $room->id,
                    (string) $period,
                ),
            );

            return $reservation->refresh();
        });
    }
}
