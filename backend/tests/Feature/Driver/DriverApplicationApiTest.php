<?php

declare(strict_types=1);

use App\Modules\Driver\Models\Driver;
use App\Modules\Driver\Models\DriverApplication;
use App\Modules\Driver\Models\Vehicle;
use App\Modules\Geo\Models\City;
use App\Modules\Identity\Models\User;
use Laravel\Sanctum\Sanctum;

function actingDriver(array $overrides = []): User
{
    $user = User::factory()->driver()->create($overrides);
    Sanctum::actingAs($user, ['driver:onboarding']);

    return $user;
}

it('does not return earnings for a user with no driver profile', function (): void {
    actingDriver();

    $this->getJson('/api/v1/driver/me')
        ->assertOk()
        ->assertJsonPath('data.driver_context.has_driver_profile', false)
        ->assertJsonPath('data.driver_context.can_go_online', false)
        ->assertJsonPath('data.driver_context.today_earnings', null)
        ->assertJsonPath('data.driver_context.reason_if_cannot_go_online', 'driver.no_profile');
});

it('keeps pending applications unable to go online', function (): void {
    $user = actingDriver();
    DriverApplication::factory()->pending()->create(['user_id' => $user->id]);

    $this->getJson('/api/v1/driver/me')
        ->assertOk()
        ->assertJsonPath('data.driver_context.has_driver_profile', false)
        ->assertJsonPath('data.driver_context.application_status', 'pending')
        ->assertJsonPath('data.driver_context.can_go_online', false);
});

it('lets an approved driver with an active vehicle go online', function (): void {
    config(['geo.index.enabled' => false]);

    $user = User::factory()->driver()->create();
    Sanctum::actingAs($user, ['driver']);

    $driver = Driver::factory()->create([
        'user_id' => $user->id,
        'status' => 'approved',
        'verification_status' => 'verified',
    ]);
    $vehicle = Vehicle::factory()->create(['driver_id' => $driver->id, 'is_active' => true]);
    $driver->update(['current_vehicle_id' => $vehicle->id]);

    $this->postJson('/api/v1/driver/status/online', [
        'lat' => 41.7151,
        'lng' => 44.8271,
    ])
        ->assertOk()
        ->assertJsonPath('data.online', true);
});

it('submitting a registration creates a pending application', function (): void {
    actingDriver();
    $city = City::factory()->create();

    $this->postJson('/api/v1/driver/application', [
        'first_name' => 'Giorgi',
        'last_name' => 'Driver',
        'personal_id' => '12345678901',
        'phone_e164' => '+995555123456',
        'city_id' => $city->id,
        'driver_type' => 'moto',
        'vehicle_type' => 'scooter_petrol',
        'vehicle_brand' => 'Honda',
        'vehicle_model' => 'PCX',
        'vehicle_plate' => 'AA-123',
        'information_confirmed' => true,
        'terms_accepted' => true,
        'privacy_accepted' => true,
    ])->assertCreated();

    $this->postJson('/api/v1/driver/application/submit')
        ->assertOk()
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonPath('driver_context.application_status', 'pending');
});

it('returns rejection reason for a rejected application', function (): void {
    $user = actingDriver();
    DriverApplication::factory()->rejected('ფოტო ბუნდოვანია')->create(['user_id' => $user->id]);

    $this->getJson('/api/v1/driver/me')
        ->assertOk()
        ->assertJsonPath('data.driver_context.application_status', 'rejected')
        ->assertJsonPath('data.driver_context.rejection_reason', 'ფოტო ბუნდოვანია')
        ->assertJsonPath('data.driver_context.can_go_online', false);
});
