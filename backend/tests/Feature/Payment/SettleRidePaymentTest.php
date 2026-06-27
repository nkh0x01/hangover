<?php

declare(strict_types=1);

use App\Modules\Driver\Models\Driver;
use App\Modules\Driver\Models\Vehicle;
use App\Modules\Geo\Models\City;
use App\Modules\Identity\Models\User;
use App\Modules\Payment\Actions\IssueRideRefund;
use App\Modules\Payment\Actions\SettleRidePayment;
use App\Modules\Payment\Models\Payment;
use App\Modules\Pricing\Models\FareEstimate;
use App\Modules\Riding\Models\Ride;
use App\Modules\Riding\StateMachine\RideStatus;
use App\Modules\Wallet\Models\Transaction;
use App\Modules\Wallet\Models\Wallet;
use App\Support\Money;
use App\Support\Ulid;
use Tests\Support\SpatialTestHelpers;

function completedRide(): Ride
{
    $city = City::factory()->create(['slug' => 'tbilisi', 'default_commission_rate' => 0.15]);
    $customer = User::factory()->create();
    $driver = Driver::factory()->create(['city_id' => $city->id]);
    $vehicle = Vehicle::factory()->create(['driver_id' => $driver->id, 'is_active' => true]);
    $driver->update(['current_vehicle_id' => $vehicle->id]);

    $estimate = FareEstimate::create([
        'ulid' => Ulid::new(),
        'customer_id' => $customer->id, 'city_id' => $city->id,
        'pickup_lat' => 41.71, 'pickup_lng' => 44.82,
        'dropoff_lat' => 41.73, 'dropoff_lng' => 44.82,
        'distance_km' => 2.0, 'duration_min' => 6,
        'base_fare' => 2.5, 'surge_multiplier' => 1.00,
        'total_amount' => 20.00, 'currency' => 'GEL',
        'expires_at' => now()->addMinutes(30),
    ]);

    $ride = SpatialTestHelpers::createRide([
        'ulid' => Ulid::new(),
        'customer_id' => $customer->id,
        'driver_id' => $driver->id,
        'vehicle_id' => $vehicle->id,
        'city_id' => $city->id,
        'status' => RideStatus::Completed,
        'pickup_address' => 'Pickup', 'dropoff_address' => 'Dropoff',
        'fare_estimate_id' => $estimate->id,
        'quoted_amount' => 20.00,
        'final_amount' => 20.00,
        'surge_multiplier' => 1.0,
        'currency' => 'GEL',
        'payment_method' => 'cash',
        'requested_at' => now()->subMinutes(10),
        'completed_at' => now(),
    ]);

    return $ride->refresh();
}

beforeEach(function (): void {
    config()->set('commission.default_rate', 0.15);
    config()->set('payment.methods.cash', 'cash');
});

it('captures a cash payment + posts driver wallet entries', function (): void {
    $ride = completedRide();

    $payment = app(SettleRidePayment::class)->execute($ride);

    expect($payment->status)->toBe('captured');
    expect($payment->method)->toBe('cash');
    expect($payment->provider)->toBe('cash');
    expect((float) $payment->amount)->toBe(20.00);

    $ride->refresh();
    expect($ride->payment_id)->toBe($payment->id);
    expect((float) $ride->commission_amount)->toBe(3.00);
    expect((float) $ride->driver_earnings)->toBe(17.00);

    $driverWallet = Wallet::query()->where('user_id', $ride->driver->user_id)->first();
    expect((float) $driverWallet->balance_cached)->toBe(17.00);

    $txs = Transaction::query()->where('wallet_id', $driverWallet->id)->get();
    expect($txs)->toHaveCount(2);
    expect($txs->where('kind', 'ride_payout')->count())->toBe(1);
    expect($txs->where('kind', 'adjustment')->count())->toBe(1);
});

it('is idempotent when run twice for the same ride', function (): void {
    $ride = completedRide();

    $first = app(SettleRidePayment::class)->execute($ride);
    $second = app(SettleRidePayment::class)->execute($ride);

    expect($second->id)->toBe($first->id);
    expect(Payment::query()->where('ride_id', $ride->id)->count())->toBe(1);

    $driverWallet = Wallet::query()->where('user_id', $ride->driver->user_id)->first();
    expect((float) $driverWallet->balance_cached)->toBe(17.00);
});

it('issues a partial refund + clawback the driver share proportionally', function (): void {
    $ride = completedRide();
    $payment = app(SettleRidePayment::class)->execute($ride);
    $admin = User::factory()->create();

    $refund = app(IssueRideRefund::class)->execute(
        payment: $payment,
        amount: Money::fromDecimal('5.00', 'GEL'),
        reason: 'driver took the long way',
        initiatedBy: $admin,
    );

    expect($refund->status)->toBe('succeeded');
    expect((float) $refund->amount)->toBe(5.00);

    $payment->refresh();
    expect($payment->status)->toBe('partially_refunded');

    $customerWallet = Wallet::query()->where('user_id', $ride->customer_id)->first();
    expect((float) $customerWallet->balance_cached)->toBe(5.00);

    // 5/20 = 25% refund. Driver clawback = 25% of 17 = 4.25.
    $driverWallet = Wallet::query()->where('user_id', $ride->driver->user_id)->first();
    expect((float) $driverWallet->balance_cached)->toBe(12.75);
});

it('marks payment as fully refunded when the whole amount is returned', function (): void {
    $ride = completedRide();
    $payment = app(SettleRidePayment::class)->execute($ride);
    $admin = User::factory()->create();

    app(IssueRideRefund::class)->execute(
        $payment,
        Money::fromDecimal('20.00', 'GEL'),
        'system fault',
        $admin,
    );

    expect($payment->refresh()->status)->toBe('refunded');
});

it('rejects a refund that would exceed the captured amount', function (): void {
    $ride = completedRide();
    $payment = app(SettleRidePayment::class)->execute($ride);
    $admin = User::factory()->create();

    expect(fn () => app(IssueRideRefund::class)->execute(
        $payment,
        Money::fromDecimal('50.00', 'GEL'),
        'too much',
        $admin,
    ))->toThrow(InvalidArgumentException::class);
});
