<?php

declare(strict_types=1);

use App\Modules\Driver\Models\Driver;
use App\Modules\Driver\Models\Vehicle;
use App\Modules\Geo\Models\City;
use App\Modules\Identity\Models\User;
use App\Modules\Pricing\Models\FareEstimate;
use App\Modules\Riding\Actions\AcceptRideOffer;
use App\Modules\Riding\Exceptions\RideNotOfferableException;
use App\Modules\Riding\Models\Ride;
use App\Modules\Riding\Models\RideOffer;
use App\Modules\Riding\StateMachine\RideStatus;
use App\Support\Ulid;
use Illuminate\Support\Facades\DB;
use Tests\Support\SpatialTestHelpers;

/**
 * The single most important correctness test in the platform: when
 * two drivers race to accept the same ride, only one must succeed.
 *
 * We simulate the race by issuing both AcceptRideOffer calls inside a
 * single PHP process. The DB row-level lock on the rides row + the
 * compound check on the offer's `response=pending` should serialise
 * them, and the second caller must throw RideNotOfferableException.
 */
function buildOfferedRide(int $offers = 2): array
{
    $city = City::factory()->create();
    $customer = User::factory()->create();
    $estimate = FareEstimate::create([
        'customer_id' => $customer->id,
        'city_id' => $city->id,
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

    $drivers = collect();
    for ($i = 0; $i < $offers; $i++) {
        $driver = Driver::factory()->create(['city_id' => $city->id]);
        Vehicle::factory()->create(['driver_id' => $driver->id, 'is_active' => true]);
        $driver->update(['current_vehicle_id' => $driver->vehicles()->first()->id]);
        $drivers->push($driver);

        RideOffer::create([
            'ride_id' => $ride->id,
            'driver_id' => $driver->id,
            'offered_at' => now(),
            'expires_at' => now()->addSeconds(12),
            'response' => 'pending',
            'distance_to_pickup_m' => 500,
            'eta_seconds' => 60,
        ]);
    }

    return ['ride' => $ride->fresh(), 'drivers' => $drivers];
}

it('only allows one driver to accept the same ride', function (): void {
    ['ride' => $ride, 'drivers' => $drivers] = buildOfferedRide(2);
    /** @var Driver $a */
    /** @var Driver $b */
    [$a, $b] = $drivers->all();

    /** @var AcceptRideOffer $action */
    $action = app(AcceptRideOffer::class);

    $action->execute($a, $ride->ulid);

    expect(fn () => $action->execute($b, $ride->ulid))
        ->toThrow(RideNotOfferableException::class);

    $ride->refresh();
    expect($ride->driver_id)->toBe($a->id)
        ->and($ride->status)->toBe(RideStatus::Accepted);
});

it('refuses a second accept attempt by the same driver', function (): void {
    ['ride' => $ride, 'drivers' => $drivers] = buildOfferedRide(1);
    /** @var Driver $a */
    [$a] = $drivers->all();

    /** @var AcceptRideOffer $action */
    $action = app(AcceptRideOffer::class);
    $action->execute($a, $ride->ulid);

    expect(fn () => $action->execute($a, $ride->ulid))
        ->toThrow(RideNotOfferableException::class);
});

it('refuses to accept an expired offer', function (): void {
    ['ride' => $ride, 'drivers' => $drivers] = buildOfferedRide(1);
    [$a] = $drivers->all();

    RideOffer::where('ride_id', $ride->id)->update(['expires_at' => now()->subSecond()]);

    /** @var AcceptRideOffer $action */
    $action = app(AcceptRideOffer::class);

    expect(fn () => $action->execute($a, $ride->ulid))
        ->toThrow(RideNotOfferableException::class);
});
