<?php

use App\Domain\Exceptions\InvalidReservationState;
use App\Domain\Reservations\Actions\CancelReservation;
use App\Models\AvailabilityCalendar;
use App\Models\Reservation;
use Tests\Support\PmsTestFactory;

beforeEach(function () {
    $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    $this->p = new PmsTestFactory();
    $this->cancel = app(CancelReservation::class);
});

it('cancels a confirmed reservation and releases availability', function () {
    $reservation = $this->p->createReservation();
    $period = new \App\Domain\Availability\Period(
        $reservation->check_in_date->toDateString(),
        $reservation->check_out_date->toDateString(),
    );

    $cancelled = $this->cancel->execute($reservation, 'guest no longer needed');

    expect($cancelled->status)->toBe(Reservation::STATUS_CANCELLED)
        ->and($cancelled->cancelled_at)->not->toBeNull()
        ->and($cancelled->cancellation_reason)->toBe('guest no longer needed');

    $rows = AvailabilityCalendar::where('room_id', $reservation->room_id)
        ->whereIn('date', $period->nightDates())
        ->get();
    foreach ($rows as $row) {
        expect($row->status)->toBe(AvailabilityCalendar::STATUS_OPEN)
            ->and($row->reservation_id)->toBeNull();
    }
});

it('writes a status history row on cancel', function () {
    $reservation = $this->p->createReservation();
    $this->cancel->execute($reservation, 'test');

    $history = $reservation->fresh()->statusHistory;
    $last = $history->last();
    expect($last->from_status)->toBe(Reservation::STATUS_CONFIRMED)
        ->and($last->to_status)->toBe(Reservation::STATUS_CANCELLED)
        ->and($last->note)->toBe('test');
});

it('refuses to cancel an already cancelled reservation', function () {
    $reservation = $this->p->createReservation();
    $this->cancel->execute($reservation, 'first');

    expect(fn () => $this->cancel->execute($reservation->fresh(), 'second'))
        ->toThrow(InvalidReservationState::class);
});

it('refuses to cancel a checked-out reservation', function () {
    $reservation = $this->p->createReservation();
    $reservation->update([
        'status' => Reservation::STATUS_CHECKED_OUT,
        'checked_in_at' => now(),
        'checked_out_at' => now(),
    ]);

    expect(fn () => $this->cancel->execute($reservation->fresh(), 'too late'))
        ->toThrow(InvalidReservationState::class);
});

it('allows a new reservation on the freed dates after a cancel', function () {
    $first = $this->p->createReservation();
    $period = new \App\Domain\Availability\Period(
        $first->check_in_date->toDateString(),
        $first->check_out_date->toDateString(),
    );
    $this->cancel->execute($first, 'release the room');

    $second = $this->p->createReservation(period: $period, room: $this->p->room(0));
    expect($second->id)->not->toBe($first->id)
        ->and($second->status)->toBe(Reservation::STATUS_CONFIRMED);
});
