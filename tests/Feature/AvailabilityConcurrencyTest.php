<?php

use App\Models\AvailabilityCalendar;
use App\Models\Property;
use App\Models\Room;
use App\Models\RoomType;
use Illuminate\Support\Facades\DB;

/*
 * The DB-level UNIQUE(room_id, date) constraint is the last line of defence
 * against overbooking. SQLite enforces this constraint just fine, so the
 * single-process invariant test below runs on every CI run.
 *
 * The TRUE multi-process race is gated on MySQL/MariaDB (FOR UPDATE row
 * locking). Set PMS_MYSQL_CONCURRENCY_DSN to enable it locally, e.g.:
 *
 *   PMS_MYSQL_CONCURRENCY_DSN="mysql://root:root@127.0.0.1:3306/pms_test" \
 *       vendor/bin/pest --filter=concurrent
 */

it('UNIQUE(room_id, date) prevents a single-process double-book', function () {
    $property = Property::factory()->create();
    $type = RoomType::factory()->create(['property_id' => $property->id]);
    $room = Room::factory()->create([
        'property_id' => $property->id,
        'room_type_id' => $type->id,
    ]);

    // Pre-populate one open availability row.
    $row = AvailabilityCalendar::create([
        'property_id' => $property->id,
        'room_id' => $room->id,
        'date' => '2026-07-01',
        'status' => AvailabilityCalendar::STATUS_OPEN,
    ]);

    // Imagine two reservation attempts that both think they "found" an open slot.
    // The first transaction wins the row update; the second tries to insert a
    // duplicate row (simulating a missing-ledger race) and is rejected.
    DB::transaction(function () use ($row) {
        $row->refresh();
        $row->update([
            'status' => AvailabilityCalendar::STATUS_BOOKED,
        ]);
    });

    expect(fn () => AvailabilityCalendar::create([
        'property_id' => $row->property_id,
        'room_id' => $row->room_id,
        'date' => '2026-07-01',
        'status' => AvailabilityCalendar::STATUS_BOOKED,
    ]))->toThrow(\Illuminate\Database\QueryException::class);
});

it('two concurrent reservation attempts: only one wins', function () {
    $dsn = getenv('PMS_MYSQL_CONCURRENCY_DSN');
    if (! $dsn) {
        $this->markTestSkipped('Concurrency test requires PMS_MYSQL_CONCURRENCY_DSN to be set.');
    }
    // Implementation deferred until the ReservationService action exists
    // (Phase 1 step 7). The harness will fork two processes that race to
    // claim the same room/date and assert exactly one of them succeeds.
    $this->markTestIncomplete('Implemented alongside ReservationService::create() in Phase 1.');
});
