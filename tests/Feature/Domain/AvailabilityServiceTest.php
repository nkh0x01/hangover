<?php

use App\Domain\Availability\AvailabilityService;
use App\Domain\Availability\Period;
use App\Domain\Exceptions\RoomNotAvailable;
use App\Models\AvailabilityCalendar;
use Tests\Support\PmsTestFactory;

beforeEach(function () {
    $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    $this->p = new PmsTestFactory();
    $this->svc = app(AvailabilityService::class);
});

it('ensureRowsExist creates one row per night idempotently', function () {
    $period = new Period('2026-05-10', '2026-05-13');

    $this->svc->ensureRowsExist($this->p->room(0), $period);
    expect(AvailabilityCalendar::where('room_id', $this->p->room(0)->id)->count())->toBe(3);

    // running it again must not duplicate
    $this->svc->ensureRowsExist($this->p->room(0), $period);
    expect(AvailabilityCalendar::where('room_id', $this->p->room(0)->id)->count())->toBe(3);
});

it('isRoomAvailable returns false when any night is booked', function () {
    $period = new Period('2026-05-10', '2026-05-13');
    $this->svc->ensureRowsExist($this->p->room(0), $period);

    AvailabilityCalendar::where('room_id', $this->p->room(0)->id)
        ->where('date', '2026-05-11')
        ->update(['status' => 'booked']);

    expect($this->svc->isRoomAvailable($this->p->room(0), $period))->toBeFalse();
});

it('isRoomAvailable returns true when rows do not exist yet', function () {
    $period = new Period('2026-12-01', '2026-12-04');
    expect($this->svc->isRoomAvailable($this->p->room(0), $period))->toBeTrue();
});

it('block prevents subsequent reservation, unblock restores availability', function () {
    $period = new Period('2026-05-10', '2026-05-13');
    $this->svc->block($this->p->room(0), $period, 'maintenance');
    expect($this->svc->isRoomAvailable($this->p->room(0), $period))->toBeFalse();

    $this->svc->unblock($this->p->room(0), $period);
    expect($this->svc->isRoomAvailable($this->p->room(0), $period))->toBeTrue();
});

it('block fails if any of the dates is already booked', function () {
    $reservation = $this->p->createReservation(
        period: new Period('2026-05-10', '2026-05-13'),
        room: $this->p->room(0),
    );

    expect(fn () => $this->svc->block(
        $this->p->room(0),
        new Period('2026-05-12', '2026-05-15'),
        'overlap test',
    ))->toThrow(RoomNotAvailable::class);
});

it('availableRoomsForType returns only rooms with all open nights', function () {
    $period = new Period('2026-05-10', '2026-05-13');
    $this->svc->block($this->p->room(0), $period, 'block r101');

    $available = $this->svc->availableRoomsForType($this->p->standardType, $period);
    expect($available)->toHaveCount(1)
        ->and($available->first()->id)->toBe($this->p->room(1)->id);
});

it('matrix returns one entry per (room, date) covering virtual open rows', function () {
    $period = new Period('2026-05-10', '2026-05-12');
    $matrix = $this->svc->matrix($this->p->property, $period);

    expect($matrix)->toHaveCount(2);
    foreach ($this->p->rooms as $room) {
        expect($matrix[$room->id])->toHaveCount(2)
            ->and($matrix[$room->id]['2026-05-10']->status)->toBe('open');
    }
});
