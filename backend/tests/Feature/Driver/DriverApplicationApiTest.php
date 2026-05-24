<?php

declare(strict_types=1);

use App\Modules\Driver\Models\Driver;
use App\Modules\Driver\Models\DriverApplication;
use App\Modules\Driver\Models\Vehicle;
use App\Modules\Geo\Models\City;
use App\Modules\Identity\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

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
    Storage::fake('local');
    config(['drivers.docs_disk' => 'local']);
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
        'vehicle_year' => 2024,
        'vehicle_color' => 'Black',
        'vehicle_plate' => 'AA-123',
        'information_confirmed' => true,
        'terms_accepted' => true,
        'privacy_accepted' => true,
    ])->assertCreated();

    uploadDriverApplicationDocuments($this);

    $this->postJson('/api/v1/driver/application/submit')
        ->assertOk()
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonPath('driver_context.application_status', 'pending')
        ->assertJsonPath('driver_context.can_go_online', false);
});

it('marks incomplete application submissions as needs completion', function (): void {
    actingDriver();

    $this->postJson('/api/v1/driver/application', [
        'first_name' => 'Giorgi',
        'phone_e164' => '555123456',
    ])->assertCreated()
        ->assertJsonPath('data.phone_e164', '+995555123456');

    $this->postJson('/api/v1/driver/application/submit')
        ->assertOk()
        ->assertJsonPath('data.status', 'needs_completion')
        ->assertJsonPath('data.decision_reason', 'application.incomplete')
        ->assertJsonPath('driver_context.application_status', 'needs_completion')
        ->assertJsonPath('driver_context.can_submit_application', true)
        ->assertJsonPath('driver_context.can_go_online', false);
});

it('does not auto approve complete applications by default', function (): void {
    actingDriver();
    Storage::fake('local');
    config(['drivers.docs_disk' => 'local']);
    $city = City::factory()->create();

    $this->postJson('/api/v1/driver/application', [
        'first_name' => 'Nino',
        'last_name' => 'Driver',
        'personal_id' => '12345678902',
        'phone_e164' => '+995555123457',
        'city_id' => $city->id,
        'driver_type' => 'moto',
        'vehicle_type' => 'scooter_petrol',
        'vehicle_brand' => 'Yamaha',
        'vehicle_model' => 'NMax',
        'vehicle_year' => 2024,
        'vehicle_color' => 'Yellow',
        'vehicle_plate' => 'BB-123',
        'information_confirmed' => true,
        'terms_accepted' => true,
        'privacy_accepted' => true,
    ])->assertCreated();

    uploadDriverApplicationDocuments($this);

    $this->postJson('/api/v1/driver/application/submit')
        ->assertOk()
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonPath('data.can_auto_approve', false);
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

function uploadDriverApplicationDocuments(TestCase $test): void
{
    foreach (['id_front', 'id_back', 'license_front', 'license_back', 'vehicle_registration', 'vehicle_photo', 'selfie'] as $type) {
        $test->post('/api/v1/driver/application/documents', [
            'doc_type' => $type,
            'file' => UploadedFile::fake()->image($type.'.jpg', 600, 400),
        ])->assertCreated();
    }
}
