<?php

namespace App\Domain\Availability;

use App\Domain\Exceptions\AvailabilityLedgerCorrupt;
use App\Domain\Exceptions\RoomNotAvailable;
use App\Models\AvailabilityCalendar;
use App\Models\Property;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\RoomType;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AvailabilityService
{
    /**
     * Idempotently make sure an availability row exists for every night in
     * the period for the given room. New rows are created with status=open.
     * Safe to call from a transaction.
     */
    public function ensureRowsExist(Room $room, Period $period): void
    {
        $existing = AvailabilityCalendar::query()
            ->where('room_id', $room->id)
            ->whereIn('date', $period->nightDates())
            ->get(['date'])
            ->map(fn (AvailabilityCalendar $r) => $r->date->toDateString())
            ->all();

        $missing = array_diff($period->nightDates(), $existing);
        if ($missing === []) {
            return;
        }

        $rows = [];
        foreach ($missing as $date) {
            $rows[] = [
                'property_id' => $room->property_id,
                'room_id'     => $room->id,
                'date'        => $date,
                'status'      => AvailabilityCalendar::STATUS_OPEN,
                'created_at'  => now(),
                'updated_at'  => now(),
            ];
        }

        AvailabilityCalendar::query()->upsert(
            $rows,
            ['room_id', 'date'],
            ['status', 'updated_at'],
        );
    }

    /**
     * True when every night in the period is currently open for this room.
     * This is a non-locking read intended for quoting / UI. Use reserve()
     * for the authoritative claim under a transaction.
     */
    public function isRoomAvailable(Room $room, Period $period, ?Reservation $excluding = null): bool
    {
        $query = AvailabilityCalendar::query()
            ->where('room_id', $room->id)
            ->whereIn('date', $period->nightDates());

        $rows = $query->get();

        if ($rows->count() !== $period->nightCount()) {
            // Missing rows are treated as "open" — they'll be created on reserve().
            $rows = $this->fillVirtualOpenRows($rows, $room, $period);
        }

        foreach ($rows as $row) {
            if ($row->status === AvailabilityCalendar::STATUS_OPEN) {
                continue;
            }
            if ($excluding && $row->reservation_id === $excluding->id) {
                continue;
            }
            return false;
        }

        return true;
    }

    /**
     * @return Collection<int, Room> the rooms of this type that are open
     *                              for every night in the period
     */
    public function availableRoomsForType(RoomType $type, Period $period, ?Reservation $excluding = null): Collection
    {
        return $type->rooms()
            ->get()
            ->filter(fn (Room $room) => $this->isRoomAvailable($room, $period, $excluding))
            ->values();
    }

    /**
     * Authoritatively claim every night in the period for this reservation.
     * MUST be called from inside a DB::transaction(). Acquires a row-level
     * lock (SELECT … FOR UPDATE) on the availability rows before mutating
     * them. Throws RoomNotAvailable on conflict; the DB-level UNIQUE(room_id,
     * date) is the last line of defence if a parallel writer slips past
     * the lock window.
     */
    public function reserve(Reservation $reservation, Room $room, Period $period): void
    {
        $this->ensureRowsExist($room, $period);

        $rows = AvailabilityCalendar::query()
            ->where('room_id', $room->id)
            ->whereIn('date', $period->nightDates())
            ->orderBy('date')
            ->lockForUpdate()
            ->get();

        if ($rows->count() !== $period->nightCount()) {
            $missing = array_values(array_diff(
                $period->nightDates(),
                $rows->map(fn (AvailabilityCalendar $r) => $r->date->toDateString())->all(),
            ));
            throw AvailabilityLedgerCorrupt::missingRows($room->id, $missing);
        }

        foreach ($rows as $row) {
            if ($row->status !== AvailabilityCalendar::STATUS_OPEN) {
                throw RoomNotAvailable::forRoom($room->id, $period);
            }
        }

        AvailabilityCalendar::query()
            ->whereIn('id', $rows->pluck('id'))
            ->update([
                'status'         => AvailabilityCalendar::STATUS_BOOKED,
                'reservation_id' => $reservation->id,
                'updated_at'     => now(),
            ]);
    }

    /**
     * Release every availability row currently held by this reservation.
     * Only releases rows whose reservation_id matches; stale calls are
     * therefore safe.
     */
    public function release(Reservation $reservation): void
    {
        AvailabilityCalendar::query()
            ->where('reservation_id', $reservation->id)
            ->where('status', AvailabilityCalendar::STATUS_BOOKED)
            ->update([
                'status'         => AvailabilityCalendar::STATUS_OPEN,
                'reservation_id' => null,
                'updated_at'     => now(),
            ]);
    }

    public function block(Room $room, Period $period, string $reason, ?int $byUserId = null): void
    {
        DB::transaction(function () use ($room, $period, $reason, $byUserId): void {
            $this->ensureRowsExist($room, $period);

            $rows = AvailabilityCalendar::query()
                ->where('room_id', $room->id)
                ->whereIn('date', $period->nightDates())
                ->lockForUpdate()
                ->get();

            foreach ($rows as $row) {
                if ($row->status === AvailabilityCalendar::STATUS_BOOKED) {
                    throw RoomNotAvailable::forRoom($room->id, $period);
                }
            }

            AvailabilityCalendar::query()
                ->whereIn('id', $rows->pluck('id'))
                ->update([
                    'status'         => AvailabilityCalendar::STATUS_BLOCKED,
                    'blocked_reason' => $reason,
                    'blocked_by'     => $byUserId,
                    'updated_at'     => now(),
                ]);
        });
    }

    public function unblock(Room $room, Period $period): void
    {
        AvailabilityCalendar::query()
            ->where('room_id', $room->id)
            ->whereIn('date', $period->nightDates())
            ->where('status', AvailabilityCalendar::STATUS_BLOCKED)
            ->update([
                'status'         => AvailabilityCalendar::STATUS_OPEN,
                'blocked_reason' => null,
                'blocked_by'     => null,
                'updated_at'     => now(),
            ]);
    }

    /**
     * Returns a [room_id][date] => row map across the property for the
     * given period. Used by the calendar UI; rows that don't exist yet
     * are returned as virtual "open" rows so the UI never has gaps.
     *
     * @return array<int, array<string, AvailabilityCalendar>>
     */
    public function matrix(Property $property, Period $period): array
    {
        $rows = AvailabilityCalendar::query()
            ->where('property_id', $property->id)
            ->whereIn('date', $period->nightDates())
            ->get();

        $byKey = [];
        foreach ($rows as $row) {
            $byKey[$row->room_id][(string) $row->date->toDateString()] = $row;
        }

        $matrix = [];
        foreach ($property->rooms as $room) {
            foreach ($period->nightDates() as $date) {
                $matrix[$room->id][$date] = $byKey[$room->id][$date]
                    ?? $this->makeVirtualOpenRow($property->id, $room->id, $date);
            }
        }

        return $matrix;
    }

    private function fillVirtualOpenRows(Collection $existing, Room $room, Period $period): Collection
    {
        $byDate = $existing->keyBy(fn (AvailabilityCalendar $r) => (string) $r->date->toDateString());

        foreach ($period->nightDates() as $date) {
            if (! $byDate->has($date)) {
                $byDate->put($date, $this->makeVirtualOpenRow($room->property_id, $room->id, $date));
            }
        }

        return $byDate->values();
    }

    private function makeVirtualOpenRow(int $propertyId, int $roomId, string $date): AvailabilityCalendar
    {
        return new AvailabilityCalendar([
            'property_id' => $propertyId,
            'room_id'     => $roomId,
            'date'        => $date,
            'status'      => AvailabilityCalendar::STATUS_OPEN,
        ]);
    }
}
