<?php

declare(strict_types=1);

use App\Modules\Geo\Models\City;
use App\Modules\Identity\Models\User;
use App\Modules\Pricing\Models\FareEstimate;
use App\Modules\Riding\Models\Ride;
use App\Modules\Riding\Services\DispatchService;
use App\Modules\Riding\StateMachine\RideStatus;
use App\Support\Ulid;
use Tests\Support\SpatialTestHelpers;

it('terminates a ride as no_drivers once the search window elapses without candidates', function (): void {
    $city = City::factory()->create();
    $customer = User::factory()->create();
    $estimate = FareEstimate::create([
        'customer_id' => $customer->id, 'city_id' => $city->id,
        'pickup_lat' => 41.7151, 'pickup_lng' => 44.8271,
        'dropoff_lat' => 41.7321, 'dropoff_lng' => 44.8271,
        'distance_km' => 2.0, 'duration_min' => 6,
        'base_fare' => 2.5, 'surge_multiplier' => 1.00,
        'total_amount' => 7.5, 'currency' => 'GEL',
        'expires_at' => now()->addMinutes(30),
    ]);

    $ride = new Ride([
        'ulid' => Ulid::new(),
        'customer_id' => $customer->id,
        'city_id' => $city->id,
        'status' => RideStatus::Searching,
        'pickup_address' => 'Pickup', 'dropoff_address' => 'Dropoff',
        'fare_estimate_id' => $estimate->id,
        'quoted_amount' => 7.5, 'surge_multiplier' => 1.0, 'currency' => 'GEL',
        'payment_method' => 'cash',
        // Old enough to exceed the search window.
        'requested_at' => now()->subSeconds((int) config('realtime.offer.search_timeout_seconds', 60) + 5),
    ]);
    $ride->save();
    SpatialTestHelpers::setRidePoints($ride->id, 44.8271, 41.7151, 44.8271, 41.7321);

    /** @var DispatchService $dispatch */
    $dispatch = app(DispatchService::class);
    $dispatch->dispatchTick($ride->fresh());

    expect($ride->fresh()->status)->toBe(RideStatus::NoDrivers);
});

it('exits the loop gracefully when the ride has already advanced to a terminal state', function (): void {
    $city = City::factory()->create();
    $customer = User::factory()->create();
    $estimate = FareEstimate::create([
        'customer_id' => $customer->id, 'city_id' => $city->id,
        'pickup_lat' => 41.7151, 'pickup_lng' => 44.8271,
        'dropoff_lat' => 41.7321, 'dropoff_lng' => 44.8271,
        'distance_km' => 2.0, 'duration_min' => 6,
        'base_fare' => 2.5, 'surge_multiplier' => 1.00,
        'total_amount' => 7.5, 'currency' => 'GEL',
        'expires_at' => now()->addMinutes(30),
    ]);

    $ride = new Ride([
        'ulid' => Ulid::new(),
        'customer_id' => $customer->id,
        'city_id' => $city->id,
        'status' => RideStatus::Cancelled,
        'pickup_address' => 'Pickup', 'dropoff_address' => 'Dropoff',
        'fare_estimate_id' => $estimate->id,
        'quoted_amount' => 7.5, 'surge_multiplier' => 1.0, 'currency' => 'GEL',
        'payment_method' => 'cash',
        'requested_at' => now(),
    ]);
    $ride->save();
    SpatialTestHelpers::setRidePoints($ride->id, 44.8271, 41.7151, 44.8271, 41.7321);

    /** @var DispatchService $dispatch */
    $dispatch = app(DispatchService::class);
    $dispatch->dispatchTick($ride->fresh());

    // Status unchanged.
    expect($ride->fresh()->status)->toBe(RideStatus::Cancelled);
});
