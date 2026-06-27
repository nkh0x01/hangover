<?php

declare(strict_types=1);

use App\Modules\Driver\Models\Driver;
use App\Modules\Driver\Models\DriverShift;
use App\Modules\Driver\Models\Vehicle;
use App\Modules\Geo\Models\City;
use App\Modules\Geo\Services\DbNearbyDriverFinder;
use App\Modules\Geo\Services\LiveLocationRecorder;
use App\Modules\Identity\Models\User;
use App\Modules\Pricing\Models\FareEstimate;
use App\Modules\Riding\Events\RideOffered;
use App\Modules\Riding\Jobs\ExpireRideOffer;
use App\Modules\Riding\Jobs\OfferRideToNextDriver;
use App\Modules\Riding\Models\Ride;
use App\Modules\Riding\Models\RideOffer;
use App\Modules\Riding\Services\DispatchService;
use App\Modules\Riding\StateMachine\RideStatus;
use App\Support\Geo\Point;
use App\Support\Ulid;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\Support\SpatialTestHelpers;

function dispatchFallbackDriver(City $city, array $driverOverrides = [], ?Point $location = null, ?DateTimeInterface $recordedAt = null): array
{
    $driverUser = User::factory()->driver()->create();
    $driver = Driver::factory()->create(array_merge([
        'user_id' => $driverUser->id,
        'city_id' => $city->id,
        'status' => 'approved',
        'verification_status' => 'verified',
        'online' => true,
        'online_since' => now(),
    ], $driverOverrides));
    $vehicle = Vehicle::factory()->create([
        'driver_id' => $driver->id,
        'is_active' => true,
        'verified_at' => now(),
    ]);
    $driver->update(['current_vehicle_id' => $vehicle->id]);

    $location ??= new Point(41.7151, 44.8271);
    app(LiveLocationRecorder::class)->record(
        driver: $driver,
        location: $location,
        recordedAt: $recordedAt ?? now(),
    );
    DriverShift::query()->create([
        'driver_id' => $driver->id,
        'started_at' => $recordedAt ?? now(),
        'started_lat' => $location->lat,
        'started_lng' => $location->lng,
    ]);

    return [$driverUser, $driver->fresh(), $vehicle];
}

function dispatchFallbackRide(City $city, User $customer, RideStatus $status = RideStatus::Searching): Ride
{
    $estimate = FareEstimate::create([
        'customer_id' => $customer->id,
        'city_id' => $city->id,
        'pickup_lat' => 41.7151,
        'pickup_lng' => 44.8271,
        'dropoff_lat' => 41.7321,
        'dropoff_lng' => 44.8271,
        'distance_km' => 2.0,
        'duration_min' => 6,
        'base_fare' => 2.5,
        'surge_multiplier' => 1.00,
        'total_amount' => 7.5,
        'currency' => 'GEL',
        'expires_at' => now()->addMinutes(30),
    ]);

    $ride = SpatialTestHelpers::createRide([
        'ulid' => Ulid::new(),
        'customer_id' => $customer->id,
        'city_id' => $city->id,
        'status' => $status,
        'pickup_address' => 'Freedom Square',
        'dropoff_address' => 'Vake Park',
        'fare_estimate_id' => $estimate->id,
        'quoted_amount' => 7.5,
        'surge_multiplier' => 1.0,
        'currency' => 'GEL',
        'payment_method' => 'cash',
        'requested_at' => now(),
    ]);

    return $ride->fresh();
}

it('finds an online approved driver from the DB fallback when Redis is unavailable', function (): void {
    config([
        'geo.index.enabled' => true,
        'geo.index.connection' => 'geo',
        'database.redis.geo.host' => '127.0.0.1',
        'database.redis.geo.port' => 63790,
    ]);

    $city = City::factory()->create();
    [, $driver] = dispatchFallbackDriver($city);

    $rows = app(DbNearbyDriverFinder::class)->nearby($city->id, new Point(41.7151, 44.8271), 3.0);

    expect(array_column($rows, 'driver_id'))->toContain($driver->id);
});

it('ignores offline drivers in the DB fallback', function (): void {
    $city = City::factory()->create();
    [, $driver] = dispatchFallbackDriver($city, ['online' => false, 'online_since' => null]);

    $rows = app(DbNearbyDriverFinder::class)->nearby($city->id, new Point(41.7151, 44.8271), 3.0);

    expect(array_column($rows, 'driver_id'))->not->toContain($driver->id);
});

it('ignores stale locations in the DB fallback', function (): void {
    config(['geo.index.fallback_recent_seconds' => 300]);

    $city = City::factory()->create();
    [, $driver] = dispatchFallbackDriver($city, recordedAt: now()->subMinutes(10));

    $rows = app(DbNearbyDriverFinder::class)->nearby($city->id, new Point(41.7151, 44.8271), 3.0);

    expect(array_column($rows, 'driver_id'))->not->toContain($driver->id);
});

it('creates an offer for a nearby online driver without Redis', function (): void {
    Queue::fake([ExpireRideOffer::class, OfferRideToNextDriver::class]);
    config([
        'geo.index.enabled' => true,
        'database.redis.geo.host' => '127.0.0.1',
        'database.redis.geo.port' => 63790,
    ]);

    $city = City::factory()->create();
    [, $driver] = dispatchFallbackDriver($city);
    $customer = User::factory()->create();
    $ride = dispatchFallbackRide($city, $customer);

    app(DispatchService::class)->dispatchTick($ride);

    $offer = RideOffer::query()->where('ride_id', $ride->id)->where('driver_id', $driver->id)->first();

    expect($offer)->not->toBeNull()
        ->and($offer?->response)->toBe('pending')
        ->and($ride->fresh()->status)->toBe(RideStatus::Offered);
});

it('sets no_drivers without a 500 when no nearby drivers exist', function (): void {
    config(['geo.index.enabled' => false]);

    $city = City::factory()->create();
    $customer = User::factory()->create();
    $ride = dispatchFallbackRide($city, $customer, RideStatus::Searching);
    $ride->update(['requested_at' => now()->subSeconds((int) config('realtime.offer.search_timeout_seconds', 60) + 5)]);

    app(DispatchService::class)->dispatchTick($ride->fresh());

    expect($ride->fresh()->status)->toBe(RideStatus::NoDrivers);
});

it('lets the driver fetch and accept a DB-fallback offer', function (): void {
    // Asserts on pickup/dropoff coordinates read from the MySQL spatial
    // columns, which SQLite omits.
    if (! SpatialTestHelpers::requiresMysql()) {
        $this->markTestSkipped('Reads MySQL spatial coordinates');
    }

    Queue::fake([ExpireRideOffer::class, OfferRideToNextDriver::class]);
    config(['geo.index.enabled' => false]);

    $city = City::factory()->create();
    [$driverUser, $driver] = dispatchFallbackDriver($city);
    $customer = User::factory()->create();
    $ride = dispatchFallbackRide($city, $customer);
    app(DispatchService::class)->dispatchTick($ride);

    Sanctum::actingAs($driverUser, ['driver']);

    $this->getJson('/api/v1/driver/offers/active')
        ->assertOk()
        ->assertJsonPath('data.ride_ulid', $ride->ulid)
        ->assertJsonPath('data.pickup.lat', 41.7151)
        ->assertJsonPath('data.pickup.lng', 44.8271)
        ->assertJsonPath('data.dropoff.lat', 41.7321)
        ->assertJsonPath('data.dropoff.lng', 44.8271);

    $this->postJson("/api/v1/driver/offers/{$ride->ulid}/accept")
        ->assertOk()
        ->assertJsonPath('data.id', $ride->ulid)
        ->assertJsonPath('data.driver.id', $driverUser->ulid);

    $ride->refresh();

    expect($ride->driver_id)->toBe($driver->id)
        ->and($ride->status)->toBe(RideStatus::Accepted);
});

it('includes pickup and dropoff coordinates in offered event payload', function (): void {
    // Coordinates come from the MySQL spatial columns, absent on SQLite.
    if (! SpatialTestHelpers::requiresMysql()) {
        $this->markTestSkipped('Reads MySQL spatial coordinates');
    }

    $city = City::factory()->create();
    [, $driver] = dispatchFallbackDriver($city);
    $customer = User::factory()->create();
    $ride = dispatchFallbackRide($city, $customer);

    $event = RideOffered::build($ride, $driver, 500, now()->addSeconds(12));
    $payload = $event->broadcastWith();

    expect($payload['pickup'])->toMatchArray([
        'address' => 'Freedom Square',
        'lat' => 41.7151,
        'lng' => 44.8271,
    ])->and($payload['dropoff'])->toMatchArray([
        'address' => 'Vake Park',
        'lat' => 41.7321,
        'lng' => 44.8271,
    ]);
});

it('returns validation errors for invalid customer ride requests', function (): void {
    $customer = User::factory()->create();
    Sanctum::actingAs($customer, ['customer']);

    $this->postJson('/api/v1/customer/rides', [
        'pickup' => ['lat' => 200, 'lng' => 44.8271, 'address' => 'Bad'],
    ])
        ->assertUnprocessable()
        ->assertJsonPath('error.code', 'validation.failed');
});

it('requires customer auth for ride requests', function (): void {
    $this->postJson('/api/v1/customer/rides', [])
        ->assertUnauthorized();
});
