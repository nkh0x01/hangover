<?php

use App\Domain\Exceptions\InvalidReservationState;
use App\Domain\Exceptions\RoomAlreadyOccupied;
use App\Domain\Reservations\Actions\CheckInReservation;
use App\Domain\Reservations\Actions\CheckOutReservation;
use App\Models\Invoice;
use App\Models\Reservation;
use App\Models\Room;
use Tests\Support\PmsTestFactory;

beforeEach(function () {
    $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    $this->p = new PmsTestFactory();
    $this->checkIn  = app(CheckInReservation::class);
    $this->checkOut = app(CheckOutReservation::class);
});

it('checks in a confirmed reservation and marks the room occupied', function () {
    $reservation = $this->p->createReservation();

    $after = $this->checkIn->execute($reservation);

    expect($after->status)->toBe(Reservation::STATUS_CHECKED_IN)
        ->and($after->checked_in_at)->not->toBeNull()
        ->and(Room::find($reservation->room_id)->status)->toBe(Room::STATUS_OCCUPIED);

    $last = $after->statusHistory->last();
    expect($last->from_status)->toBe(Reservation::STATUS_CONFIRMED)
        ->and($last->to_status)->toBe(Reservation::STATUS_CHECKED_IN);
});

it('refuses to check in a pending reservation', function () {
    $reservation = $this->p->createReservation(initialStatus: Reservation::STATUS_PENDING);

    expect(fn () => $this->checkIn->execute($reservation))
        ->toThrow(InvalidReservationState::class);
});

it('refuses to check in twice', function () {
    $reservation = $this->p->createReservation();
    $this->checkIn->execute($reservation);

    expect(fn () => $this->checkIn->execute($reservation->fresh()))
        ->toThrow(InvalidReservationState::class);
});

it('refuses to check in when the assigned room is in maintenance', function () {
    $reservation = $this->p->createReservation();
    Room::where('id', $reservation->room_id)->update(['status' => Room::STATUS_MAINTENANCE]);

    expect(fn () => $this->checkIn->execute($reservation))
        ->toThrow(RoomAlreadyOccupied::class);
});

it('checks out a checked-in reservation, marks the room dirty and generates an invoice', function () {
    $reservation = $this->p->createReservation();
    $this->checkIn->execute($reservation);

    $invoice = $this->checkOut->execute($reservation->fresh());

    expect($invoice)->toBeInstanceOf(Invoice::class)
        ->and($invoice->number)->toStartWith('TST-')
        ->and((float) $invoice->total)->toBe((float) $reservation->fresh()->grand_total)
        ->and($invoice->lines)->toHaveCount($reservation->nights);

    $reservation->refresh();
    expect($reservation->status)->toBe(Reservation::STATUS_CHECKED_OUT)
        ->and($reservation->checked_out_at)->not->toBeNull()
        ->and(Room::find($reservation->room_id)->status)->toBe(Room::STATUS_DIRTY);
});

it('accepts extra charges at check-out and reflects them in totals and invoice', function () {
    $reservation = $this->p->createReservation();
    $this->checkIn->execute($reservation);

    $invoice = $this->checkOut->execute($reservation->fresh(), null, [
        ['description' => 'Late checkout fee', 'amount' => 25, 'type' => 'fee'],
        ['description' => 'Breakage',         'amount' => 50, 'type' => 'fee'],
    ]);

    $reservation->refresh();
    expect((float) $reservation->extras_total)->toBe(75.0)
        ->and((float) $reservation->grand_total)->toBe((float) $reservation->room_rate_total + 75.0)
        // 1 room night line is at minimum 2 nights (nextPeriod default) + 2 fee lines.
        ->and($invoice->lines->count())->toBeGreaterThanOrEqual(4);
});

it('refuses check-out for a reservation that is not checked in', function () {
    $reservation = $this->p->createReservation();

    expect(fn () => $this->checkOut->execute($reservation))
        ->toThrow(InvalidReservationState::class);
});

it('refuses to check out twice', function () {
    $reservation = $this->p->createReservation();
    $this->checkIn->execute($reservation);
    $this->checkOut->execute($reservation->fresh());

    expect(fn () => $this->checkOut->execute($reservation->fresh()))
        ->toThrow(InvalidReservationState::class);
});
