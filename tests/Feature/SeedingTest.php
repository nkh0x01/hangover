<?php

use App\Models\AvailabilityCalendar;
use App\Models\Property;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

it('full seed produces the expected baseline state', function () {
    $this->seed();

    expect(Property::count())->toBe(1)
        ->and(RoomType::count())->toBe(4)
        ->and(Room::count())->toBe(12)
        ->and(Role::count())->toBe(5)
        ->and(Permission::count())->toBe(count(RolesAndPermissionsSeeder::PERMISSIONS))
        ->and(AvailabilityCalendar::count())->toBe(12 * 90);
});

it('admin user is super_admin and bypasses every permission', function () {
    $this->seed();
    $admin = User::where('email', 'admin@example.test')->firstOrFail();

    expect($admin->hasRole(RolesAndPermissionsSeeder::ROLE_SUPER_ADMIN))->toBeTrue()
        ->and($admin->can('reservations.create'))->toBeTrue()
        ->and($admin->can('users.manage'))->toBeTrue()
        ->and($admin->can('audit.view'))->toBeTrue();
});

it('reception user has reception permissions but not admin ones', function () {
    $this->seed();
    $reception = User::where('email', 'reception@example.test')->firstOrFail();

    expect($reception->can('reservations.create'))->toBeTrue()
        ->and($reception->can('reservations.check_in'))->toBeTrue()
        ->and($reception->can('payments.record'))->toBeTrue()
        ->and($reception->can('users.manage'))->toBeFalse()
        ->and($reception->can('rooms.manage'))->toBeFalse();
});

it('housekeeping user can only change room cleaning status', function () {
    $this->seed();
    $hk = User::factory()->create();
    $hk->assignRole(RolesAndPermissionsSeeder::ROLE_HOUSEKEEPING);

    expect($hk->can('rooms.change_cleaning_status'))->toBeTrue()
        ->and($hk->can('reservations.create'))->toBeFalse()
        ->and($hk->can('payments.record'))->toBeFalse();
});

it('accountant user can view payments and reports but not record them', function () {
    $this->seed();
    $acc = User::factory()->create();
    $acc->assignRole(RolesAndPermissionsSeeder::ROLE_ACCOUNTANT);

    expect($acc->can('payments.view'))->toBeTrue()
        ->and($acc->can('reports.view'))->toBeTrue()
        ->and($acc->can('payments.record'))->toBeFalse()
        ->and($acc->can('reservations.create'))->toBeFalse();
});
