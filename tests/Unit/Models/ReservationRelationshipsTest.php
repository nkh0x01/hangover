<?php

use App\Models\Guest;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Property;
use App\Models\Reservation;
use App\Models\ReservationCharge;
use App\Models\ReservationNight;
use App\Models\ReservationStatusHistory;
use App\Models\Room;
use App\Models\RoomType;

beforeEach(function () {
    $this->property = Property::factory()->create();
    $this->type = RoomType::factory()->create(['property_id' => $this->property->id]);
    $this->room = Room::factory()->create([
        'property_id' => $this->property->id,
        'room_type_id' => $this->type->id,
    ]);
    $this->guest = Guest::factory()->create(['property_id' => $this->property->id]);
    $this->reservation = Reservation::factory()->create([
        'property_id'  => $this->property->id,
        'guest_id'     => $this->guest->id,
        'room_id'      => $this->room->id,
        'room_type_id' => $this->type->id,
    ]);
});

it('belongs to property, room, room type, lead guest', function () {
    $r = $this->reservation->fresh();
    expect($r->property->id)->toBe($this->property->id)
        ->and($r->room->id)->toBe($this->room->id)
        ->and($r->roomType->id)->toBe($this->type->id)
        ->and($r->leadGuest->id)->toBe($this->guest->id);
});

it('has many nights, charges, payments, status history', function () {
    ReservationNight::factory()->create([
        'reservation_id' => $this->reservation->id,
        'room_id'        => $this->room->id,
        'date'           => '2026-06-01',
    ]);
    ReservationCharge::factory()->create(['reservation_id' => $this->reservation->id]);
    Payment::factory()->create([
        'property_id'    => $this->property->id,
        'reservation_id' => $this->reservation->id,
    ]);
    ReservationStatusHistory::factory()->create(['reservation_id' => $this->reservation->id]);

    $r = $this->reservation->fresh();
    expect($r->nightsBreakdown)->toHaveCount(1)
        ->and($r->charges)->toHaveCount(1)
        ->and($r->payments)->toHaveCount(1)
        ->and($r->statusHistory)->toHaveCount(1);
});

it('has one invoice', function () {
    Invoice::factory()->create([
        'property_id'    => $this->property->id,
        'reservation_id' => $this->reservation->id,
    ]);

    expect($this->reservation->fresh()->invoice)->not->toBeNull();
});

it('attaches additional guests via reservation_guests pivot', function () {
    $extra = Guest::factory()->create(['property_id' => $this->property->id]);
    $this->reservation->guests()->attach($extra->id, ['is_lead' => false]);

    expect($this->reservation->fresh()->guests)->toHaveCount(1)
        ->and($this->reservation->fresh()->guests->first()->pivot->is_lead)->toBeFalsy();
});
