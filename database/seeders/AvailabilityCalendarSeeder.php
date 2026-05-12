<?php

namespace Database\Seeders;

use App\Models\AvailabilityCalendar;
use App\Models\Property;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class AvailabilityCalendarSeeder extends Seeder
{
    /**
     * Pre-populate 90 days of open availability rows for every room.
     * Reservation creation later updates these rows in place.
     */
    public function run(): void
    {
        $property = Property::query()->orderBy('id')->first();
        if (! $property) {
            return;
        }

        $start = Carbon::today();
        $end   = $start->copy()->addDays(90);

        $rooms = $property->rooms()->get(['id']);

        foreach ($rooms as $room) {
            $rows = [];
            for ($d = $start->copy(); $d->lt($end); $d->addDay()) {
                $rows[] = [
                    'property_id' => $property->id,
                    'room_id' => $room->id,
                    'date' => $d->toDateString(),
                    'status' => AvailabilityCalendar::STATUS_OPEN,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
            // upsert respects UNIQUE(room_id, date) — safe to re-run.
            AvailabilityCalendar::query()->upsert(
                $rows,
                ['room_id', 'date'],
                ['status', 'updated_at'],
            );
        }
    }
}
