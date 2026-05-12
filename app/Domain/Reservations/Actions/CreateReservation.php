<?php

namespace App\Domain\Reservations\Actions;

use App\Domain\Availability\AvailabilityService;
use App\Domain\Exceptions\RoomNotAvailable;
use App\Domain\Pricing\PricingService;
use App\Domain\Reservations\Data\CreateReservationData;
use App\Domain\Reservations\Support\ReservationCodeGenerator;
use App\Domain\Reservations\Support\ReservationStatusWriter;
use App\Domain\Reservations\Support\ReservationTotals;
use App\Models\Reservation;
use App\Models\ReservationNight;
use App\Models\Room;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CreateReservation
{
    public function __construct(
        private readonly AvailabilityService $availability,
        private readonly PricingService $pricing,
        private readonly ReservationCodeGenerator $codes,
        private readonly ReservationStatusWriter $statuses,
        private readonly ReservationTotals $totals,
    ) {
    }

    public function execute(CreateReservationData $data): Reservation
    {
        $this->validateInput($data);

        return DB::transaction(function () use ($data): Reservation {
            $room = $this->resolveRoom($data);

            $quote = $this->pricing->priceForStay($data->roomType, $data->period);

            $reservation = Reservation::create([
                'code'             => $this->codes->generate($data->property),
                'property_id'      => $data->property->id,
                'guest_id'         => $data->guest->id,
                'room_id'          => $room->id,
                'room_type_id'     => $data->roomType->id,
                'check_in_date'    => $data->period->checkIn->toDateString(),
                'check_out_date'   => $data->period->checkOut->toDateString(),
                'nights'           => $data->period->nightCount(),
                'adults'           => $data->adults,
                'children'         => $data->children,
                'source'           => $data->source,
                'external_reference' => $data->externalReference,
                'status'           => $data->initialStatus,
                'payment_status'   => Reservation::PAYMENT_UNPAID,
                'currency'         => $quote->currency,
                'special_requests' => $data->specialRequests,
                'internal_notes'   => $data->internalNotes,
                'created_by'       => $data->actor?->id,
                'updated_by'       => $data->actor?->id,
            ]);

            // Will throw RoomNotAvailable on conflict; the surrounding
            // DB::transaction guarantees we don't leave a half-written
            // reservation in that case.
            $this->availability->reserve($reservation, $room, $data->period);

            $this->snapshotNights($reservation, $room, $quote);

            $this->totals->recompute($reservation);

            $this->statuses->record(
                $reservation,
                null,
                $reservation->status,
                $data->actor?->id,
                'Reservation created',
            );

            return $reservation->refresh();
        });
    }

    private function validateInput(CreateReservationData $data): void
    {
        if ($data->roomType->property_id !== $data->property->id) {
            throw new InvalidArgumentException('Room type does not belong to the given property.');
        }
        if ($data->guest->property_id !== $data->property->id) {
            throw new InvalidArgumentException('Guest does not belong to the given property.');
        }
        if ($data->room && $data->room->property_id !== $data->property->id) {
            throw new InvalidArgumentException('Room does not belong to the given property.');
        }
        if ($data->room && $data->room->room_type_id !== $data->roomType->id) {
            throw new InvalidArgumentException('Room is not of the requested type.');
        }

        $allowed = [
            Reservation::STATUS_PENDING,
            Reservation::STATUS_CONFIRMED,
        ];
        if (! in_array($data->initialStatus, $allowed, true)) {
            throw new InvalidArgumentException(
                'Initial status must be one of: '.implode(', ', $allowed),
            );
        }

        if ($data->adults < 1) {
            throw new InvalidArgumentException('At least one adult is required.');
        }

        $capacity = $data->roomType->max_occupancy;
        if (($data->adults + $data->children) > $capacity) {
            throw new InvalidArgumentException(sprintf(
                'Occupancy %d exceeds the room type max of %d.',
                $data->adults + $data->children,
                $capacity,
            ));
        }
    }

    private function resolveRoom(CreateReservationData $data): Room
    {
        if ($data->room) {
            if (! $this->availability->isRoomAvailable($data->room, $data->period)) {
                throw RoomNotAvailable::forRoom($data->room->id, $data->period);
            }
            return $data->room;
        }

        // Auto-pick the lowest-numbered room of the requested type that is
        // open across the whole period. Deterministic ordering avoids the
        // "different room every refresh" calendar churn.
        $candidates = $data->roomType->rooms()->orderBy('number')->get();
        foreach ($candidates as $room) {
            if ($this->availability->isRoomAvailable($room, $data->period)) {
                return $room;
            }
        }

        throw RoomNotAvailable::forRoomType($data->roomType->id, $data->period);
    }

    private function snapshotNights(Reservation $reservation, Room $room, \App\Domain\Pricing\StayQuote $quote): void
    {
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
    }
}
