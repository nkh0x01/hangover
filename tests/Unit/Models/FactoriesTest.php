<?php

use App\Models\AvailabilityCalendar;
use App\Models\Guest;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\Payment;
use App\Models\Property;
use App\Models\Reservation;
use App\Models\ReservationCharge;
use App\Models\ReservationNight;
use App\Models\ReservationStatusHistory;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;

it('all model factories build valid persisted rows', function () {
    expect(Property::factory()->create())->toBeInstanceOf(Property::class)
        ->and(RoomType::factory()->create())->toBeInstanceOf(RoomType::class)
        ->and(Room::factory()->create())->toBeInstanceOf(Room::class)
        ->and(Guest::factory()->create())->toBeInstanceOf(Guest::class)
        ->and(Reservation::factory()->create())->toBeInstanceOf(Reservation::class)
        ->and(ReservationNight::factory()->create())->toBeInstanceOf(ReservationNight::class)
        ->and(ReservationCharge::factory()->create())->toBeInstanceOf(ReservationCharge::class)
        ->and(ReservationStatusHistory::factory()->create())->toBeInstanceOf(ReservationStatusHistory::class)
        ->and(AvailabilityCalendar::factory()->create())->toBeInstanceOf(AvailabilityCalendar::class)
        ->and(Payment::factory()->create())->toBeInstanceOf(Payment::class)
        ->and(Invoice::factory()->create())->toBeInstanceOf(Invoice::class)
        ->and(InvoiceLine::factory()->create())->toBeInstanceOf(InvoiceLine::class)
        ->and(User::factory()->create())->toBeInstanceOf(User::class);
});

it('room factory states produce the right status', function () {
    expect(Room::factory()->occupied()->create()->status)->toBe(Room::STATUS_OCCUPIED)
        ->and(Room::factory()->dirty()->create()->status)->toBe(Room::STATUS_DIRTY)
        ->and(Room::factory()->maintenance()->create()->status)->toBe(Room::STATUS_MAINTENANCE);
});

it('reservation factory states produce the right status', function () {
    expect(Reservation::factory()->checkedIn()->create()->status)->toBe(Reservation::STATUS_CHECKED_IN)
        ->and(Reservation::factory()->checkedOut()->create()->status)->toBe(Reservation::STATUS_CHECKED_OUT)
        ->and(Reservation::factory()->cancelled()->create()->status)->toBe(Reservation::STATUS_CANCELLED);
});
