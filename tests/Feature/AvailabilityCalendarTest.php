<?php

use App\Models\AvailabilityCalendar;
use App\Models\Property;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\RoomType;
use Illuminate\Database\QueryException;

it('enforces UNIQUE(room_id, date) on availability_calendar', function () {
    $property = Property::factory()->create();
    $type = RoomType::factory()->create(['property_id' => $property->id]);
    $room = Room::factory()->create([
        'property_id' => $property->id,
        'room_type_id' => $type->id,
    ]);

    AvailabilityCalendar::create([
        'property_id' => $property->id,
        'room_id' => $room->id,
        'date' => '2026-06-01',
        'status' => AvailabilityCalendar::STATUS_OPEN,
    ]);

    expect(fn () => AvailabilityCalendar::create([
        'property_id' => $property->id,
        'room_id' => $room->id,
        'date' => '2026-06-01',
        'status' => AvailabilityCalendar::STATUS_BOOKED,
    ]))->toThrow(QueryException::class);
});

it('allows the same date for two different rooms', function () {
    $property = Property::factory()->create();
    $type = RoomType::factory()->create(['property_id' => $property->id]);
    $r1 = Room::factory()->create(['property_id' => $property->id, 'room_type_id' => $type->id]);
    $r2 = Room::factory()->create(['property_id' => $property->id, 'room_type_id' => $type->id]);

    AvailabilityCalendar::create([
        'property_id' => $property->id, 'room_id' => $r1->id,
        'date' => '2026-06-01', 'status' => AvailabilityCalendar::STATUS_BOOKED,
    ]);
    AvailabilityCalendar::create([
        'property_id' => $property->id, 'room_id' => $r2->id,
        'date' => '2026-06-01', 'status' => AvailabilityCalendar::STATUS_BOOKED,
    ]);

    expect(AvailabilityCalendar::count())->toBe(2);
});

it('allows the same room for two different dates', function () {
    $property = Property::factory()->create();
    $type = RoomType::factory()->create(['property_id' => $property->id]);
    $room = Room::factory()->create(['property_id' => $property->id, 'room_type_id' => $type->id]);

    AvailabilityCalendar::create([
        'property_id' => $property->id, 'room_id' => $room->id,
        'date' => '2026-06-01', 'status' => AvailabilityCalendar::STATUS_BOOKED,
    ]);
    AvailabilityCalendar::create([
        'property_id' => $property->id, 'room_id' => $room->id,
        'date' => '2026-06-02', 'status' => AvailabilityCalendar::STATUS_BOOKED,
    ]);

    expect(AvailabilityCalendar::count())->toBe(2);
});

it('half-open interval: a stay May 10-12 occupies nights of May 10 and May 11 only', function () {
    // Sanity check on the convention reservation_nights will follow.
    // A new reservation that checks in on May 12 should NOT collide with
    // a reservation that checks out on May 12.
    $checkIn  = '2026-05-10';
    $checkOut = '2026-05-12';

    $nights = collect();
    $cursor = new DateTimeImmutable($checkIn);
    $end    = new DateTimeImmutable($checkOut);
    while ($cursor < $end) {
        $nights->push($cursor->format('Y-m-d'));
        $cursor = $cursor->modify('+1 day');
    }

    expect($nights->all())->toBe(['2026-05-10', '2026-05-11'])
        ->and($nights)->not->toContain('2026-05-12');
});

it('links to a reservation when booked', function () {
    $property = Property::factory()->create();
    $type = RoomType::factory()->create(['property_id' => $property->id]);
    $room = Room::factory()->create(['property_id' => $property->id, 'room_type_id' => $type->id]);
    $reservation = Reservation::factory()->create([
        'property_id' => $property->id,
        'room_id' => $room->id,
        'room_type_id' => $type->id,
    ]);

    $row = AvailabilityCalendar::create([
        'property_id' => $property->id,
        'room_id' => $room->id,
        'date' => '2026-06-01',
        'status' => AvailabilityCalendar::STATUS_BOOKED,
        'reservation_id' => $reservation->id,
    ]);

    expect($row->reservation->id)->toBe($reservation->id)
        ->and($row->isBooked())->toBeTrue();
});
