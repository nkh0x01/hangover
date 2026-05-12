<?php

use App\Domain\Reservations\Actions\CancelReservation;
use App\Domain\Reservations\Actions\CheckInReservation;
use App\Domain\Reservations\Actions\CheckOutReservation;
use App\Domain\Reservations\Actions\RecordPayment;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\Room;
use OwenIt\Auditing\Models\Audit;
use Tests\Support\PmsTestFactory;

beforeEach(function () {
    $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    $this->p = new PmsTestFactory();
});

it('writes audit rows for reservation lifecycle events', function () {
    $reservation = $this->p->createReservation();

    $createdAudits = Audit::where('auditable_type', Reservation::class)
        ->where('auditable_id', $reservation->id)
        ->get();
    expect($createdAudits->where('event', 'created'))->not->toBeEmpty();

    app(CheckInReservation::class)->execute($reservation);
    app(CheckOutReservation::class)->execute($reservation->fresh());

    $afterAudits = Audit::where('auditable_type', Reservation::class)
        ->where('auditable_id', $reservation->id)
        ->get();
    expect($afterAudits->where('event', 'updated')->count())->toBeGreaterThanOrEqual(2);
});

it('writes audit rows for room status changes', function () {
    $reservation = $this->p->createReservation();
    app(CheckInReservation::class)->execute($reservation);

    $audits = Audit::where('auditable_type', Room::class)
        ->where('auditable_id', $reservation->room_id)
        ->get();
    expect($audits->where('event', 'updated'))->not->toBeEmpty();
});

it('writes audit rows for payments', function () {
    $reservation = $this->p->createReservation();
    $payment = app(RecordPayment::class)->execute(
        $reservation,
        Payment::METHOD_CASH,
        50,
    );

    $audits = Audit::where('auditable_type', Payment::class)
        ->where('auditable_id', $payment->id)
        ->get();
    expect($audits->where('event', 'created'))->not->toBeEmpty();
});

it('writes audit rows for cancel', function () {
    $reservation = $this->p->createReservation();
    app(CancelReservation::class)->execute($reservation, 'no longer needed');

    $audits = Audit::where('auditable_type', Reservation::class)
        ->where('auditable_id', $reservation->id)
        ->whereIn('event', ['updated'])
        ->get();
    $hasCancelAudit = $audits->contains(function (Audit $a) {
        $new = is_array($a->new_values) ? $a->new_values : (json_decode($a->new_values, true) ?: []);
        return ($new['status'] ?? null) === Reservation::STATUS_CANCELLED;
    });
    expect($hasCancelAudit)->toBeTrue();
});
