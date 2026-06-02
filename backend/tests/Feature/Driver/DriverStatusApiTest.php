<?php

declare(strict_types=1);

use App\Modules\Driver\Models\Driver;
use App\Modules\Driver\Models\DriverShift;
use App\Modules\Driver\Models\Vehicle;
use App\Modules\Identity\Models\User;
use Laravel\Sanctum\Sanctum;

function approvedDriverForStatus(array $driverOverrides = [], array $vehicleOverrides = []): array
{
    $user = User::factory()->driver()->create();
    $driver = Driver::factory()->create(array_merge([
        'user_id' => $user->id,
        'status' => 'approved',
        'verification_status' => 'verified',
        'online' => false,
        'online_since' => null,
        'current_vehicle_id' => null,
    ], $driverOverrides));
    $vehicle = Vehicle::factory()->create(array_merge([
        'driver_id' => $driver->id,
        'is_active' => true,
        'verified_at' => now(),
    ], $vehicleOverrides));
    $driver->update(['current_vehicle_id' => $vehicle->id]);

    Sanctum::actingAs($user, ['driver']);

    return [$user, $driver->fresh(), $vehicle];
}

it('lets an approved driver with an active current vehicle go online', function (): void {
    config(['geo.index.enabled' => false]);

    [, $driver, $vehicle] = approvedDriverForStatus();

    $this->postJson('/api/v1/driver/status/online', [
        'lat' => 41.7151,
        'lng' => 44.8271,
    ])
        ->assertOk()
        ->assertJsonPath('data.online', true);

    $driver->refresh();

    expect($driver->online)->toBeTrue()
        ->and($driver->current_vehicle_id)->toBe($vehicle->id)
        ->and(DriverShift::query()
            ->where('driver_id', $driver->id)
            ->whereNull('ended_at')
            ->count())->toBe(1);
});

it('keeps already-online requests idempotent', function (): void {
    config(['geo.index.enabled' => false]);

    [, $driver] = approvedDriverForStatus([
        'online' => true,
        'online_since' => now()->subHour(),
    ]);
    $driver->shifts()->create([
        'started_at' => $driver->online_since,
        'started_lat' => 41.7151,
        'started_lng' => 44.8271,
    ]);

    $this->postJson('/api/v1/driver/status/online', [
        'lat' => 41.7151,
        'lng' => 44.8271,
    ])->assertOk();

    $this->postJson('/api/v1/driver/status/online', [
        'lat' => 41.7151,
        'lng' => 44.8271,
    ])->assertOk();

    expect(DriverShift::query()
        ->where('driver_id', $driver->id)
        ->whereNull('ended_at')
        ->count())->toBe(1);
});

it('returns validation errors when location is missing', function (): void {
    config(['geo.index.enabled' => false]);

    approvedDriverForStatus();

    $this->postJson('/api/v1/driver/status/online')
        ->assertUnprocessable()
        ->assertJsonPath('error.code', 'validation.failed')
        ->assertJsonPath('error.details.fields.lat.0', 'The lat field is required.')
        ->assertJsonPath('error.details.fields.lng.0', 'The lng field is required.');
});

it('does not let an onboarding driver go online', function (): void {
    config(['geo.index.enabled' => false]);

    [$user] = approvedDriverForStatus();
    Sanctum::actingAs($user, ['driver:onboarding']);

    $this->postJson('/api/v1/driver/status/online', [
        'lat' => 41.7151,
        'lng' => 44.8271,
    ])->assertForbidden();
});

it('returns a vehicle-specific forbidden response when no active vehicle exists', function (): void {
    config(['geo.index.enabled' => false]);

    $user = User::factory()->driver()->create();
    Driver::factory()->create([
        'user_id' => $user->id,
        'status' => 'approved',
        'verification_status' => 'verified',
        'online' => false,
        'online_since' => null,
        'current_vehicle_id' => null,
    ]);
    Sanctum::actingAs($user, ['driver']);

    $this->postJson('/api/v1/driver/status/online', [
        'lat' => 41.7151,
        'lng' => 44.8271,
    ])
        ->assertForbidden()
        ->assertJsonPath('error.code', 'driver.no_active_vehicle');
});

it('does not fail the online request when the geo index is unavailable', function (): void {
    config([
        'geo.index.enabled' => true,
        'geo.index.connection' => 'geo',
        'database.redis.geo.host' => '127.0.0.1',
        'database.redis.geo.port' => 63790,
    ]);

    [, $driver] = approvedDriverForStatus();

    $this->postJson('/api/v1/driver/status/online', [
        'lat' => 41.7151,
        'lng' => 44.8271,
    ])
        ->assertOk()
        ->assertJsonPath('data.online', true);

    expect($driver->fresh()->online)->toBeTrue();
});

it('does not fail offline when the geo index is unavailable', function (): void {
    config([
        'geo.index.enabled' => true,
        'geo.index.connection' => 'geo',
        'database.redis.geo.host' => '127.0.0.1',
        'database.redis.geo.port' => 63790,
    ]);

    [, $driver] = approvedDriverForStatus([
        'online' => true,
        'online_since' => now()->subMinutes(15),
    ]);
    $driver->shifts()->create([
        'started_at' => $driver->online_since,
        'started_lat' => 41.7151,
        'started_lng' => 44.8271,
    ]);

    $this->postJson('/api/v1/driver/status/offline')
        ->assertOk()
        ->assertJsonPath('data.online', false);

    $driver->refresh();

    expect($driver->online)->toBeFalse()
        ->and($driver->online_since)->toBeNull()
        ->and(DriverShift::query()
            ->where('driver_id', $driver->id)
            ->whereNull('ended_at')
            ->count())->toBe(0);
});
