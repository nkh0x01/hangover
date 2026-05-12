<?php

use App\Domain\Reservations\Actions\RecordPayment;
use App\Models\Payment;
use App\Models\Reservation;
use Tests\Support\PmsTestFactory;

beforeEach(function () {
    $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    $this->p = new PmsTestFactory();
    $this->record = app(RecordPayment::class);
});

it('records a cash payment and recomputes payment_status to partial when under grand_total', function () {
    $reservation = $this->p->createReservation();
    $grand = (float) $reservation->grand_total;
    expect($grand)->toBeGreaterThan(0.0);

    $this->record->execute($reservation, Payment::METHOD_CASH, $grand - 50);

    $reservation->refresh();
    expect((float) $reservation->paid_total)->toBe($grand - 50)
        ->and($reservation->payment_status)->toBe(Reservation::PAYMENT_PARTIAL);
});

it('flips to paid when payments meet or exceed grand_total', function () {
    $reservation = $this->p->createReservation();
    $grand = (float) $reservation->grand_total;

    $this->record->execute($reservation, Payment::METHOD_CASH, $grand);

    $reservation->refresh();
    expect((float) $reservation->paid_total)->toBe($grand)
        ->and($reservation->payment_status)->toBe(Reservation::PAYMENT_PAID);
});

it('aggregates split payments across methods', function () {
    $reservation = $this->p->createReservation();
    $grand = (float) $reservation->grand_total;

    $this->record->execute($reservation, Payment::METHOD_CASH, 50);
    $this->record->execute($reservation, Payment::METHOD_CARD, $grand - 50);

    $reservation->refresh();
    expect((float) $reservation->paid_total)->toBe($grand)
        ->and($reservation->payment_status)->toBe(Reservation::PAYMENT_PAID)
        ->and($reservation->payments)->toHaveCount(2);
});

it('keeps status unpaid when no completed payments have been recorded', function () {
    $reservation = $this->p->createReservation();
    expect($reservation->payment_status)->toBe(Reservation::PAYMENT_UNPAID);
});

it('rejects an unsupported payment method', function () {
    $reservation = $this->p->createReservation();
    expect(fn () => $this->record->execute($reservation, 'crypto', 100))
        ->toThrow(InvalidArgumentException::class);
});

it('rejects a zero-amount payment', function () {
    $reservation = $this->p->createReservation();
    expect(fn () => $this->record->execute($reservation, Payment::METHOD_CASH, 0))
        ->toThrow(InvalidArgumentException::class);
});
