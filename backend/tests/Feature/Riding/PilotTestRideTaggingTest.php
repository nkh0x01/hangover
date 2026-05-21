<?php

declare(strict_types=1);

use App\Modules\Geo\Models\City;
use App\Modules\Identity\Models\User;
use App\Modules\Pricing\Models\FareEstimate;
use App\Modules\Riding\Actions\CreateRideRequest;
use App\Modules\Riding\Dto\RideRequestData;
use App\Modules\Riding\Models\Ride;
use App\Modules\Riding\StateMachine\RideStatus;
use App\Support\Geo\Point;
use App\Support\Ulid;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Queue;

function rideRequestData(FareEstimate $estimate): RideRequestData
{
    return new RideRequestData(
        fareEstimateUlid: $estimate->ulid,
        pickup: new Point(41.7151, 44.8271),
        pickupAddress: 'Pickup',
        dropoff: new Point(41.7321, 44.8271),
        dropoffAddress: 'Dropoff',
        paymentMethod: 'cash',
    );
}

function fareEstimateFor(User $customer, City $city): FareEstimate
{
    return FareEstimate::create([
        'ulid' => Ulid::new(),
        'customer_id' => $customer->id,
        'city_id' => $city->id,
        'pickup_lat' => 41.7151, 'pickup_lng' => 44.8271,
        'dropoff_lat' => 41.7321, 'dropoff_lng' => 44.8271,
        'distance_km' => 2.0, 'duration_min' => 6,
        'base_fare' => 2.5, 'surge_multiplier' => 1.00,
        'total_amount' => 7.5, 'currency' => 'GEL',
        'expires_at' => now()->addMinutes(30),
    ]);
}

beforeEach(function (): void {
    Queue::fake();
    Bus::fake();
});

it('auto-tags rides created by configured tester phones', function (): void {
    config()->set('pilot.test_phone_numbers', ['+995599000001', '+995599000002']);
    config()->set('pilot.cohort', 'tbilisi-w1');

    $city = City::factory()->create();
    $tester = User::factory()->create(['phone_e164' => '+995599000001']);
    $estimate = fareEstimateFor($tester, $city);

    $ride = app(CreateRideRequest::class)->execute($tester, rideRequestData($estimate));

    expect($ride->is_test_ride)->toBeTrue();
    expect($ride->pilot_cohort)->toBe('tbilisi-w1');
});

it('leaves production customers untagged', function (): void {
    config()->set('pilot.test_phone_numbers', ['+995599000001']);
    config()->set('pilot.cohort', 'tbilisi-w1');

    $city = City::factory()->create();
    $real = User::factory()->create(['phone_e164' => '+995599999999']);
    $estimate = fareEstimateFor($real, $city);

    $ride = app(CreateRideRequest::class)->execute($real, rideRequestData($estimate));

    expect($ride->is_test_ride)->toBeFalse();
    expect($ride->pilot_cohort)->toBeNull();
});

it('does not tag anything when test_phone_numbers is empty', function (): void {
    config()->set('pilot.test_phone_numbers', []);
    config()->set('pilot.cohort', 'tbilisi-w1');

    $city = City::factory()->create();
    $user = User::factory()->create(['phone_e164' => '+995599000001']);
    $estimate = fareEstimateFor($user, $city);

    $ride = app(CreateRideRequest::class)->execute($user, rideRequestData($estimate));

    expect($ride->is_test_ride)->toBeFalse();
});

it('persists the flags so the admin filter sees them', function (): void {
    config()->set('pilot.test_phone_numbers', ['+995599000001']);
    config()->set('pilot.cohort', 'batumi-w1');

    $city = City::factory()->create();
    $tester = User::factory()->create(['phone_e164' => '+995599000001']);
    $estimate = fareEstimateFor($tester, $city);

    app(CreateRideRequest::class)->execute($tester, rideRequestData($estimate));

    $row = Ride::query()
        ->where('customer_id', $tester->id)
        ->where('is_test_ride', true)
        ->first();

    expect($row)->not->toBeNull();
    expect($row->status)->toBe(RideStatus::Requested);
    expect($row->pilot_cohort)->toBe('batumi-w1');
});
