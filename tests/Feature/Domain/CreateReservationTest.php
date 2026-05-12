<?php

use App\Domain\Availability\AvailabilityService;
use App\Domain\Availability\Period;
use App\Domain\Exceptions\RoomNotAvailable;
use App\Domain\Pricing\PricingService;
use App\Domain\Reservations\Actions\CreateReservation;
use App\Domain\Reservations\Data\CreateReservationData;
use App\Models\AvailabilityCalendar;
use App\Models\Reservation;
use App\Models\ReservationNight;
use Tests\Support\PmsTestFactory;

beforeEach(function () {
    $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    $this->p = new PmsTestFactory();
    $this->createReservation = app(CreateReservation::class);
});

it('creates a reservation with nights, availability rows, status history and totals', function () {
    $period = new Period('2026-06-01', '2026-06-04'); // Mon→Thu = 3 weekday nights

    $reservation = $this->createReservation->execute(new CreateReservationData(
        property:   $this->p->property,
        guest:      $this->p->guest,
        roomType:   $this->p->standardType,
        period:     $period,
        room:       $this->p->room(0),
        adults:     2,
    ));

    expect($reservation->code)->toStartWith('R-')
        ->and($reservation->nights)->toBe(3)
        ->and($reservation->status)->toBe(Reservation::STATUS_CONFIRMED)
        ->and($reservation->payment_status)->toBe(Reservation::PAYMENT_UNPAID)
        ->and((float) $reservation->room_rate_total)->toBe(300.0)
        ->and((float) $reservation->grand_total)->toBe(300.0)
        ->and($reservation->nightsBreakdown)->toHaveCount(3);

    // Availability rows for those 3 dates flipped to booked
    $rows = AvailabilityCalendar::where('room_id', $this->p->room(0)->id)
        ->whereIn('date', $period->nightDates())
        ->get();
    expect($rows)->toHaveCount(3);
    foreach ($rows as $row) {
        expect($row->status)->toBe(AvailabilityCalendar::STATUS_BOOKED)
            ->and($row->reservation_id)->toBe($reservation->id);
    }

    // Status history written
    expect($reservation->statusHistory)->toHaveCount(1)
        ->and($reservation->statusHistory->first()->from_status)->toBeNull()
        ->and($reservation->statusHistory->first()->to_status)->toBe(Reservation::STATUS_CONFIRMED);
});

it('rejects a reservation that overlaps an existing one for the same room', function () {
    $period = new Period('2026-06-10', '2026-06-13');
    $this->p->createReservation(period: $period, room: $this->p->room(0));

    expect(fn () => $this->p->createReservation(
        period: new Period('2026-06-12', '2026-06-14'),
        room:   $this->p->room(0),
    ))->toThrow(RoomNotAvailable::class);
});

it('allows back-to-back stays where checkout meets check-in (half-open)', function () {
    // Guest A occupies the nights of 2026-06-10 and 2026-06-11.
    $a = $this->p->createReservation(
        period: new Period('2026-06-10', '2026-06-12'),
        room:   $this->p->room(0),
    );
    // Guest B can therefore start on 2026-06-12 in the same room.
    $b = $this->p->createReservation(
        period: new Period('2026-06-12', '2026-06-14'),
        room:   $this->p->room(0),
    );

    expect($a->id)->not->toBe($b->id)
        ->and($a->status)->toBe(Reservation::STATUS_CONFIRMED)
        ->and($b->status)->toBe(Reservation::STATUS_CONFIRMED);
});

it('auto-picks the lowest-numbered available room of the requested type', function () {
    // Block room 101 first
    $service = app(AvailabilityService::class);
    $service->block(
        $this->p->room(0),
        new Period('2026-07-01', '2026-07-04'),
        'maintenance test',
    );

    $reservation = $this->createReservation->execute(new CreateReservationData(
        property:   $this->p->property,
        guest:      $this->p->guest,
        roomType:   $this->p->standardType,
        period:     new Period('2026-07-01', '2026-07-04'),
        adults:     1,
    ));

    expect($reservation->room_id)->toBe($this->p->room(1)->id);
});

it('throws when no rooms of the requested type are available', function () {
    foreach ($this->p->rooms as $room) {
        app(AvailabilityService::class)->block(
            $room,
            new Period('2026-08-01', '2026-08-03'),
            'block',
        );
    }

    expect(fn () => $this->createReservation->execute(new CreateReservationData(
        property:   $this->p->property,
        guest:      $this->p->guest,
        roomType:   $this->p->standardType,
        period:     new Period('2026-08-01', '2026-08-03'),
        adults:     1,
    )))->toThrow(RoomNotAvailable::class);
});

it('rejects guests outside the property', function () {
    $foreignProperty = \App\Models\Property::factory()->create();
    $foreignGuest = \App\Models\Guest::factory()->create(['property_id' => $foreignProperty->id]);

    expect(fn () => $this->createReservation->execute(new CreateReservationData(
        property:   $this->p->property,
        guest:      $foreignGuest,
        roomType:   $this->p->standardType,
        period:     new Period('2026-09-01', '2026-09-03'),
        adults:     1,
    )))->toThrow(InvalidArgumentException::class);
});

it('snapshots nightly rates exactly as priced', function () {
    $period = new Period('2026-05-14', '2026-05-18'); // Thu→Mon: 100,115,115,100
    $reservation = $this->p->createReservation(period: $period);

    $rates = $reservation->nightsBreakdown
        ->sortBy('date')
        ->pluck('nightly_rate')
        ->map(fn ($v) => (float) $v)
        ->values()
        ->all();
    expect($rates)->toBe([100.0, 115.0, 115.0, 100.0])
        ->and((float) $reservation->room_rate_total)->toBe(430.0);
});

it('rolls back the entire reservation if availability claim fails mid-transaction', function () {
    // Manually flip one row to booked under a different reservation, then
    // try CreateReservation; the second attempt must leave NO partial state.
    AvailabilityCalendar::where('room_id', $this->p->room(0)->id)
        ->where('date', '2026-10-02')
        ->delete(); // remove the seed row if any

    $other = $this->p->createReservation(
        period: new Period('2026-10-02', '2026-10-03'),
        room:   $this->p->room(0),
    );

    $beforeCount = Reservation::count();

    try {
        $this->p->createReservation(
            period: new Period('2026-10-01', '2026-10-03'),
            room:   $this->p->room(0),
        );
    } catch (\Throwable) {
        // expected
    }

    expect(Reservation::count())->toBe($beforeCount)
        ->and(ReservationNight::where('date', '2026-10-01')->count())->toBe(0);
});
