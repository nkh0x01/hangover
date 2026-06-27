<?php

declare(strict_types=1);

use App\Modules\Driver\Models\Driver;
use App\Modules\Driver\Models\Vehicle;
use App\Modules\Geo\Models\City;
use App\Modules\Identity\Models\User;
use App\Modules\Pricing\Models\FareEstimate;
use App\Modules\Riding\Actions\AcceptRideOffer;
use App\Modules\Riding\Actions\CancelRide;
use App\Modules\Riding\Actions\CompleteTrip;
use App\Modules\Riding\Actions\DriverArrived;
use App\Modules\Riding\Actions\DriverArriving;
use App\Modules\Riding\Actions\StartTrip;
use App\Modules\Riding\Models\RideOffer;
use App\Modules\Riding\Services\RideStateMachine;
use App\Modules\Riding\StateMachine\RideStatus;
use App\Support\Exceptions\DomainException;
use App\Support\Ulid;
use Tests\Support\SpatialTestHelpers;

function makeOfferedRide(): array
{
    $city = City::factory()->create();
    $customer = User::factory()->create();
    $driver = Driver::factory()->create(['city_id' => $city->id]);
    $vehicle = Vehicle::factory()->create(['driver_id' => $driver->id, 'is_active' => true]);
    $driver->update(['current_vehicle_id' => $vehicle->id]);

    $estimate = FareEstimate::create([
        'customer_id' => $customer->id, 'city_id' => $city->id,
        'pickup_lat' => 41.7151, 'pickup_lng' => 44.8271,
        'dropoff_lat' => 41.7321, 'dropoff_lng' => 44.8271,
        'distance_km' => 2.0, 'duration_min' => 6,
        'base_fare' => 2.5, 'surge_multiplier' => 1.00,
        'total_amount' => 7.5, 'currency' => 'GEL',
        'expires_at' => now()->addMinutes(30),
    ]);

    $ride = SpatialTestHelpers::createRide([
        'ulid' => Ulid::new(),
        'customer_id' => $customer->id,
        'city_id' => $city->id,
        'status' => RideStatus::Offered,
        'pickup_address' => 'Pickup', 'dropoff_address' => 'Dropoff',
        'fare_estimate_id' => $estimate->id,
        'quoted_amount' => 7.5, 'surge_multiplier' => 1.0, 'currency' => 'GEL',
        'payment_method' => 'cash',
        'requested_at' => now(),
    ]);

    RideOffer::create([
        'ride_id' => $ride->id,
        'driver_id' => $driver->id,
        'offered_at' => now(),
        'expires_at' => now()->addSeconds(12),
        'response' => 'pending',
        'distance_to_pickup_m' => 500,
        'eta_seconds' => 60,
    ]);

    return compact('ride', 'driver', 'customer');
}

it('walks the happy path offered → completed', function (): void {
    ['ride' => $ride, 'driver' => $driver] = makeOfferedRide();

    app(AcceptRideOffer::class)->execute($driver, $ride->ulid);
    $ride->refresh();
    expect($ride->status)->toBe(RideStatus::Accepted);

    app(DriverArriving::class)->execute($driver, $ride->refresh());
    expect($ride->refresh()->status)->toBe(RideStatus::DriverArriving);

    app(DriverArrived::class)->execute($driver, $ride->refresh());
    expect($ride->refresh()->status)->toBe(RideStatus::DriverArrived);

    app(StartTrip::class)->execute($driver, $ride->refresh());
    expect($ride->refresh()->status)->toBe(RideStatus::InProgress);

    app(CompleteTrip::class)->execute($driver, $ride->refresh());
    $ride->refresh();
    expect($ride->status)->toBe(RideStatus::Completed)
        ->and((float) $ride->final_amount)->toBeGreaterThan(0.0);
});

it('lets the customer cancel before pickup', function (): void {
    ['ride' => $ride, 'driver' => $driver, 'customer' => $customer] = makeOfferedRide();
    app(AcceptRideOffer::class)->execute($driver, $ride->ulid);

    app(CancelRide::class)->execute($customer, $ride->refresh(), 'changed my mind');

    $ride->refresh();
    expect($ride->status)->toBe(RideStatus::Cancelled)
        ->and($ride->cancellation_reason)->toBe('customer_cancelled');
});

it('refuses an illegal jump from offered to in_progress', function (): void {
    ['ride' => $ride] = makeOfferedRide();

    $sm = app(RideStateMachine::class);

    expect(fn () => $sm->transition($ride->refresh(), RideStatus::InProgress, 'system'))
        ->toThrow(DomainException::class);
});
