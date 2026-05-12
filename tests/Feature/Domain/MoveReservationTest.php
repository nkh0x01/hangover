<?php

use App\Domain\Availability\Period;
use App\Domain\Exceptions\InvalidReservationState;
use App\Domain\Exceptions\RoomNotAvailable;
use App\Domain\Reservations\Actions\CheckInReservation;
use App\Domain\Reservations\Actions\MoveReservation;
use App\Models\AvailabilityCalendar;
use App\Models\Reservation;
use Tests\Support\PmsTestFactory;

beforeEach(function () {
    $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    $this->p = new PmsTestFactory();
    $this->move = app(MoveReservation::class);
});

it('moves a reservation to a new date range, releasing old nights and locking new ones', function () {
    $reservation = $this->p->createReservation(
        period: new Period('2026-06-01', '2026-06-03'),
        room: $this->p->room(0),
    );

    $this->move->execute(
        $reservation,
        new Period('2026-06-10', '2026-06-12'),
        null,
    );

    $reservation->refresh();
    expect($reservation->check_in_date->toDateString())->toBe('2026-06-10')
        ->and($reservation->check_out_date->toDateString())->toBe('2026-06-12')
        ->and($reservation->nights)->toBe(2);

    // Old dates released
    $old = AvailabilityCalendar::where('room_id', $this->p->room(0)->id)
        ->whereIn('date', ['2026-06-01', '2026-06-02'])
        ->get();
    foreach ($old as $row) {
        expect($row->status)->toBe(AvailabilityCalendar::STATUS_OPEN);
    }

    // New dates booked
    $new = AvailabilityCalendar::where('room_id', $this->p->room(0)->id)
        ->whereIn('date', ['2026-06-10', '2026-06-11'])
        ->get();
    foreach ($new as $row) {
        expect($row->status)->toBe(AvailabilityCalendar::STATUS_BOOKED)
            ->and($row->reservation_id)->toBe($reservation->id);
    }
});

it('moves to a different room', function () {
    $reservation = $this->p->createReservation(room: $this->p->room(0));

    $this->move->execute($reservation, null, $this->p->room(1));

    $reservation->refresh();
    expect($reservation->room_id)->toBe($this->p->room(1)->id);

    $row = AvailabilityCalendar::where('room_id', $this->p->room(1)->id)
        ->where('date', $reservation->check_in_date->toDateString())
        ->first();
    expect($row->status)->toBe(AvailabilityCalendar::STATUS_BOOKED);
});

it('rolls back when the new range conflicts with another reservation', function () {
    $a = $this->p->createReservation(
        period: new Period('2026-07-01', '2026-07-04'),
        room: $this->p->room(0),
    );
    $b = $this->p->createReservation(
        period: new Period('2026-07-10', '2026-07-13'),
        room: $this->p->room(0),
    );

    expect(fn () => $this->move->execute(
        $b,
        new Period('2026-07-02', '2026-07-04'), // overlaps a
        $this->p->room(0),
    ))->toThrow(RoomNotAvailable::class);

    // Original nights still held
    $b->refresh();
    expect($b->check_in_date->toDateString())->toBe('2026-07-10');
    $row = AvailabilityCalendar::where('room_id', $this->p->room(0)->id)
        ->where('date', '2026-07-10')
        ->first();
    expect($row->status)->toBe(AvailabilityCalendar::STATUS_BOOKED)
        ->and($row->reservation_id)->toBe($b->id);
});

it('refuses to move a checked-in reservation', function () {
    $reservation = $this->p->createReservation();
    app(CheckInReservation::class)->execute($reservation);

    expect(fn () => $this->move->execute(
        $reservation->fresh(),
        new Period('2027-01-01', '2027-01-03'),
    ))->toThrow(InvalidReservationState::class);
});
