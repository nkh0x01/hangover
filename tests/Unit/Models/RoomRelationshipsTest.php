<?php

use App\Models\AvailabilityCalendar;
use App\Models\Property;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\RoomType;

it('room belongs to property and room type, has many reservations and availability rows', function () {
    $property = Property::factory()->create();
    $type = RoomType::factory()->create(['property_id' => $property->id]);
    $room = Room::factory()->create([
        'property_id' => $property->id,
        'room_type_id' => $type->id,
    ]);

    Reservation::factory()->count(2)->create([
        'property_id' => $property->id,
        'room_id' => $room->id,
        'room_type_id' => $type->id,
    ]);
    AvailabilityCalendar::factory()->create([
        'property_id' => $property->id,
        'room_id' => $room->id,
    ]);

    expect($room->property->id)->toBe($property->id)
        ->and($room->roomType->id)->toBe($type->id)
        ->and($room->reservations)->toHaveCount(2)
        ->and($room->availability)->toHaveCount(1);
});

it('room status constants are well-defined', function () {
    expect(Room::STATUSES)->toContain('available', 'occupied', 'dirty', 'clean', 'maintenance', 'blocked');
});
