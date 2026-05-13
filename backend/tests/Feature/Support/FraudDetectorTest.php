<?php

declare(strict_types=1);

use App\Modules\Identity\Models\User;
use App\Modules\Riding\Models\Ride;
use App\Modules\Riding\StateMachine\RideStatus;
use App\Modules\Support\Models\FraudFlag;
use App\Modules\Support\Services\FraudDetector;
use App\Support\Ulid;

beforeEach(function (): void {
    config()->set('safety.cancellation_storm.count', 3);
    config()->set('safety.cancellation_storm.window_hours', 2);
    config()->set('safety.implausible_speed_kmh', 200.0);
    config()->set('safety.multi_device.max_devices', 4);
});

function cancelledRide(User $customer, int $minutesAgo): Ride
{
    return Ride::create([
        'ulid' => Ulid::new(),
        'customer_id' => $customer->id,
        'city_id' => \App\Modules\Geo\Models\City::factory()->create()->id,
        'status' => RideStatus::Cancelled,
        'pickup_address' => 'A', 'dropoff_address' => 'B',
        'quoted_amount' => 1.0, 'surge_multiplier' => 1.0,
        'currency' => 'GEL', 'payment_method' => 'cash',
        'requested_at' => now()->subMinutes($minutesAgo + 5),
        'cancelled_at' => now()->subMinutes($minutesAgo),
    ]);
}

it('raises a ride_fraud flag on cancellation storm', function (): void {
    $customer = User::factory()->create();
    cancelledRide($customer, 30);
    cancelledRide($customer, 20);
    $latest = cancelledRide($customer, 5);

    $flag = app(FraudDetector::class)->onRideStatusChange($latest);

    expect($flag)->not->toBeNull();
    expect($flag->kind)->toBe('ride_fraud');
    expect($flag->severity)->toBe('warn');
    expect($flag->evidence['pattern'])->toBe('cancellation_storm');
});

it('does not raise a duplicate flag within the same window', function (): void {
    $customer = User::factory()->create();
    cancelledRide($customer, 30);
    cancelledRide($customer, 20);
    $latest = cancelledRide($customer, 5);

    app(FraudDetector::class)->onRideStatusChange($latest);
    app(FraudDetector::class)->onRideStatusChange($latest);

    expect(FraudFlag::query()->where('user_id', $customer->id)->where('kind', 'ride_fraud')->count())->toBe(1);
});

it('does not flag below the threshold', function (): void {
    $customer = User::factory()->create();
    $only = cancelledRide($customer, 10);

    $flag = app(FraudDetector::class)->onRideStatusChange($only);

    expect($flag)->toBeNull();
});

it('flags implausible-speed heartbeats', function (): void {
    $user = User::factory()->create();

    $flag = app(FraudDetector::class)->onDriverHeartbeat($user, 250.0);

    expect($flag)->not->toBeNull();
    expect($flag->kind)->toBe('manipulated_location');
    expect((float) $flag->evidence['speed_kmh'])->toBe(250.0);
});

it('does not flag plausible speed', function (): void {
    $user = User::factory()->create();
    expect(app(FraudDetector::class)->onDriverHeartbeat($user, 65.0))->toBeNull();
});

it('flags multi-device threshold breach as info severity', function (): void {
    $user = User::factory()->create();

    $flag = app(FraudDetector::class)->onDeviceRegistered($user, 6);

    expect($flag)->not->toBeNull();
    expect($flag->severity)->toBe('info');
});
