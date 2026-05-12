<?php

use App\Models\Guest;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Property;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;

it('property has many users, rooms, room types, guests, reservations, payments, invoices', function () {
    $property = Property::factory()->create();

    $type = RoomType::factory()->create(['property_id' => $property->id]);
    $room = Room::factory()->create(['property_id' => $property->id, 'room_type_id' => $type->id]);
    $guest = Guest::factory()->create(['property_id' => $property->id]);
    $reservation = Reservation::factory()->create([
        'property_id' => $property->id,
        'guest_id'    => $guest->id,
        'room_id'     => $room->id,
        'room_type_id' => $type->id,
    ]);
    Payment::factory()->create([
        'property_id'    => $property->id,
        'reservation_id' => $reservation->id,
    ]);
    Invoice::factory()->create([
        'property_id'    => $property->id,
        'reservation_id' => $reservation->id,
    ]);
    User::factory()->create(['property_id' => $property->id]);

    expect($property->roomTypes)->toHaveCount(1)
        ->and($property->rooms)->toHaveCount(1)
        ->and($property->guests)->toHaveCount(1)
        ->and($property->reservations)->toHaveCount(1)
        ->and($property->payments)->toHaveCount(1)
        ->and($property->invoices)->toHaveCount(1)
        ->and($property->users)->toHaveCount(1);
});
