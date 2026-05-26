<?php

declare(strict_types=1);

use App\Modules\Driver\Models\Driver;
use App\Modules\Driver\Models\DriverApplication;
use App\Modules\Driver\Models\Vehicle;
use App\Modules\Driver\Services\DriverApplicationApprovalService;
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

it('creates and links a driver and vehicle when approving an application', function (): void {
    $user = User::factory()->driver()->create();
    $application = DriverApplication::factory()->pending()->create([
        'user_id' => $user->id,
        'vehicle_type' => 'scooter_petrol',
        'vehicle_brand' => 'Honda',
        'vehicle_model' => 'PCX',
        'vehicle_year' => 2024,
        'vehicle_color' => 'Yellow',
        'vehicle_plate' => 'APP-001',
    ]);

    $approved = app(DriverApplicationApprovalService::class)->approve($application);
    $driver = Driver::query()->where('user_id', $user->id)->firstOrFail();
    $vehicle = Vehicle::query()->where('driver_id', $driver->id)->firstOrFail();

    expect($approved->status)->toBe('approved')
        ->and($approved->driver_id)->toBe($driver->id)
        ->and($approved->vehicle_id)->toBe($vehicle->id)
        ->and($driver->fresh()->current_vehicle_id)->toBe($vehicle->id)
        ->and($vehicle->type)->toBe('scooter_petrol')
        ->and($vehicle->brand)->toBe('Honda')
        ->and($vehicle->model)->toBe('PCX')
        ->and($vehicle->year)->toBe(2024)
        ->and($vehicle->color)->toBe('Yellow')
        ->and($vehicle->plate)->toBe('APP-001')
        ->and($vehicle->is_active)->toBeTrue()
        ->and($vehicle->verified_at)->not->toBeNull();
});

it('reuses the linked driver vehicle when approval is repeated', function (): void {
    $user = User::factory()->driver()->create();
    $application = DriverApplication::factory()->pending()->create([
        'user_id' => $user->id,
        'vehicle_type' => 'scooter_petrol',
        'vehicle_brand' => 'Honda',
        'vehicle_model' => 'PCX',
        'vehicle_year' => 2024,
        'vehicle_color' => 'Yellow',
        'vehicle_plate' => 'APP-002',
    ]);
    $approval = app(DriverApplicationApprovalService::class);

    $firstApproval = $approval->approve($application);
    $secondApproval = $approval->approve($firstApproval->fresh());
    $driver = Driver::query()->where('user_id', $user->id)->firstOrFail();

    expect(Vehicle::query()->where('driver_id', $driver->id)->where('plate', 'APP-002')->count())->toBe(1)
        ->and($secondApproval->vehicle_id)->toBe($firstApproval->vehicle_id)
        ->and($driver->fresh()->current_vehicle_id)->toBe($firstApproval->vehicle_id);
});

it('reuses an existing same-driver vehicle with the same plate during approval', function (): void {
    $user = User::factory()->driver()->create();
    $driver = Driver::factory()->create([
        'user_id' => $user->id,
        'status' => 'approved',
        'verification_status' => 'verified',
        'current_vehicle_id' => null,
    ]);
    $vehicle = Vehicle::factory()->create([
        'driver_id' => $driver->id,
        'brand' => 'Old',
        'model' => 'Vehicle',
        'plate' => 'APP-005',
    ]);
    $application = DriverApplication::factory()->pending()->create([
        'user_id' => $user->id,
        'driver_id' => $driver->id,
        'vehicle_id' => null,
        'vehicle_type' => 'scooter_petrol',
        'vehicle_brand' => 'Honda',
        'vehicle_model' => 'PCX',
        'vehicle_year' => 2024,
        'vehicle_color' => 'Yellow',
        'vehicle_plate' => 'APP-005',
    ]);

    $approved = app(DriverApplicationApprovalService::class)->approve($application);

    expect(Vehicle::query()->where('driver_id', $driver->id)->where('plate', 'APP-005')->count())->toBe(1)
        ->and($approved->vehicle_id)->toBe($vehicle->id)
        ->and($vehicle->fresh()->brand)->toBe('Honda')
        ->and($driver->fresh()->current_vehicle_id)->toBe($vehicle->id);
});

it('repair command fixes approved applications missing driver and vehicle links', function (): void {
    $user = User::factory()->driver()->create();
    $application = DriverApplication::factory()->create([
        'user_id' => $user->id,
        'status' => 'approved',
        'driver_id' => null,
        'vehicle_id' => null,
        'vehicle_type' => 'scooter_petrol',
        'vehicle_brand' => 'Honda',
        'vehicle_model' => 'PCX',
        'vehicle_year' => 2024,
        'vehicle_color' => 'Yellow',
        'vehicle_plate' => 'APP-003',
        'reviewed_at' => now(),
    ]);

    $this->artisan('drivers:repair-approved-applications')
        ->assertExitCode(0);

    $application->refresh();
    $driver = Driver::query()->where('user_id', $user->id)->firstOrFail();
    $vehicle = Vehicle::query()->where('driver_id', $driver->id)->where('plate', 'APP-003')->firstOrFail();

    expect($application->driver_id)->toBe($driver->id)
        ->and($application->vehicle_id)->toBe($vehicle->id)
        ->and($driver->fresh()->current_vehicle_id)->toBe($vehicle->id);
});

it('returns an approved driver context after application approval', function (): void {
    $user = User::factory()->driver()->create();
    $application = DriverApplication::factory()->pending()->create([
        'user_id' => $user->id,
        'vehicle_plate' => 'APP-004',
    ]);
    app(DriverApplicationApprovalService::class)->approve($application);

    Sanctum::actingAs($user, ['driver']);

    $this->getJson('/api/v1/driver/me')
        ->assertOk()
        ->assertJsonPath('data.driver_context.has_driver_profile', true)
        ->assertJsonPath('data.driver_context.can_go_online', true)
        ->assertJsonPath('data.driver_context.reason_if_cannot_go_online', null);
});

it('returns a vehicle-specific reason for approved drivers without a vehicle', function (): void {
    $user = User::factory()->driver()->create();
    Driver::factory()->create([
        'user_id' => $user->id,
        'status' => 'approved',
        'verification_status' => 'verified',
        'current_vehicle_id' => null,
    ]);

    Sanctum::actingAs($user, ['driver']);

    $this->getJson('/api/v1/driver/me')
        ->assertOk()
        ->assertJsonPath('data.driver_context.has_driver_profile', true)
        ->assertJsonPath('data.driver_context.vehicle_status', 'missing')
        ->assertJsonPath('data.driver_context.can_go_online', false)
        ->assertJsonPath('data.driver_context.reason_if_cannot_go_online', 'driver.missing_vehicle');
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
