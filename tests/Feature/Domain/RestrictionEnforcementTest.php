<?php

use App\Domain\Availability\Period;
use App\Domain\Exceptions\StayRestrictionViolated;
use App\Domain\Reservations\Actions\CreateReservation;
use App\Domain\Reservations\Data\CreateReservationData;
use App\Models\DailyRoomPrice;
use Tests\Support\PmsTestFactory;

beforeEach(function () {
    $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    $this->p = new PmsTestFactory();
});

it('CreateReservation refuses a stay shorter than min_stay', function () {
    DailyRoomPrice::create([
        'property_id' => $this->p->property->id,
        'room_type_id' => $this->p->standardType->id,
        'date' => '2026-07-15',
        'min_stay' => 3,
        'source' => DailyRoomPrice::SOURCE_MANUAL,
    ]);

    expect(fn () => app(CreateReservation::class)->execute(new CreateReservationData(
        property: $this->p->property,
        guest: $this->p->guest,
        roomType: $this->p->standardType,
        period: new Period('2026-07-15', '2026-07-17'), // 2 nights — below 3
        room: $this->p->room(0),
    )))->toThrow(StayRestrictionViolated::class);
});

it('CreateReservation refuses CTA on the arrival day', function () {
    DailyRoomPrice::create([
        'property_id' => $this->p->property->id,
        'room_type_id' => $this->p->standardType->id,
        'date' => '2026-08-01',
        'closed_to_arrival' => true,
        'source' => DailyRoomPrice::SOURCE_MANUAL,
    ]);

    expect(fn () => app(CreateReservation::class)->execute(new CreateReservationData(
        property: $this->p->property,
        guest: $this->p->guest,
        roomType: $this->p->standardType,
        period: new Period('2026-08-01', '2026-08-03'),
        room: $this->p->room(0),
    )))->toThrow(StayRestrictionViolated::class);
});

it('CreateReservation refuses CTD on the departure day', function () {
    DailyRoomPrice::create([
        'property_id' => $this->p->property->id,
        'room_type_id' => $this->p->standardType->id,
        'date' => '2026-08-15',
        'closed_to_departure' => true,
        'source' => DailyRoomPrice::SOURCE_MANUAL,
    ]);

    expect(fn () => app(CreateReservation::class)->execute(new CreateReservationData(
        property: $this->p->property,
        guest: $this->p->guest,
        roomType: $this->p->standardType,
        period: new Period('2026-08-13', '2026-08-15'),
        room: $this->p->room(0),
    )))->toThrow(StayRestrictionViolated::class);
});

it('CreateReservation succeeds when all restrictions are satisfied', function () {
    DailyRoomPrice::create([
        'property_id' => $this->p->property->id,
        'room_type_id' => $this->p->standardType->id,
        'date' => '2026-09-01',
        'min_stay' => 2,
        'source' => DailyRoomPrice::SOURCE_MANUAL,
    ]);

    $r = app(CreateReservation::class)->execute(new CreateReservationData(
        property: $this->p->property,
        guest: $this->p->guest,
        roomType: $this->p->standardType,
        period: new Period('2026-09-01', '2026-09-04'), // 3 nights >= 2
        room: $this->p->room(0),
    ));

    expect($r->id)->toBeGreaterThan(0);
});
