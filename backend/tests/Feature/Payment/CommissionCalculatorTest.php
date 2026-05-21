<?php

declare(strict_types=1);

use App\Modules\Driver\Models\Driver;
use App\Modules\Geo\Models\City;
use App\Modules\Pricing\Services\CommissionCalculator;
use App\Support\Money;

beforeEach(function (): void {
    config()->set('commission.default_rate', 0.15);
    config()->set('commission.min_amount', 0.10);
    config()->set('commission.max_amount', 50.00);
    config()->set('commission.by_city', []);
});

it('uses the default rate when no driver/city override applies', function (): void {
    $driver = Driver::factory()->create(['commission_rate_override' => null]);

    // Pass city=null so the calculator skips both city paths and lands
    // on `config('commission.default_rate')`.
    $split = app(CommissionCalculator::class)->split(
        Money::fromDecimal('20.00', 'GEL'),
        $driver,
        null,
    );

    expect($split['rate'])->toBe(0.15);
    expect($split['commission']->minor)->toBe(300);
    expect($split['driverEarnings']->minor)->toBe(1700);
});

it('honours the per-driver override before any other rule', function (): void {
    config()->set('commission.by_city', ['tbilisi' => 0.20]);
    $city = City::factory()->create(['slug' => 'tbilisi', 'default_commission_rate' => 0.10]);
    $driver = Driver::factory()->create(['city_id' => $city->id, 'commission_rate_override' => 0.05]);

    $split = app(CommissionCalculator::class)->split(
        Money::fromDecimal('10.00', 'GEL'),
        $driver,
        $city,
    );

    expect($split['rate'])->toBe(0.05);
    expect($split['commission']->minor)->toBe(50);
});

it('falls back to the city config when driver has no override', function (): void {
    config()->set('commission.by_city', ['tbilisi' => 0.20]);
    $city = City::factory()->create(['slug' => 'tbilisi', 'default_commission_rate' => 0.10]);
    $driver = Driver::factory()->create(['city_id' => $city->id, 'commission_rate_override' => null]);

    $split = app(CommissionCalculator::class)->split(
        Money::fromDecimal('10.00', 'GEL'),
        $driver,
        $city,
    );

    expect($split['rate'])->toBe(0.20);
});

it('falls back to the city default_commission_rate column when config is empty', function (): void {
    $city = City::factory()->create(['slug' => 'batumi', 'default_commission_rate' => 0.12]);
    $driver = Driver::factory()->create(['city_id' => $city->id, 'commission_rate_override' => null]);

    $split = app(CommissionCalculator::class)->split(
        Money::fromDecimal('10.00', 'GEL'),
        $driver,
        $city,
    );

    expect($split['rate'])->toBe(0.12);
});

it('clamps to the max amount when the computed commission would exceed it', function (): void {
    config()->set('commission.max_amount', 5.00);
    $city = City::factory()->create();
    $driver = Driver::factory()->create(['city_id' => $city->id, 'commission_rate_override' => 0.50]);

    $split = app(CommissionCalculator::class)->split(
        Money::fromDecimal('100.00', 'GEL'),
        $driver,
        $city,
    );

    expect($split['commission']->minor)->toBe(500);
    expect($split['driverEarnings']->minor)->toBe(9500);
});

it('preserves the fare = commission + driver invariant', function (): void {
    $city = City::factory()->create();
    $driver = Driver::factory()->create(['city_id' => $city->id, 'commission_rate_override' => 0.15]);

    $fare = Money::fromDecimal('17.33', 'GEL');
    $split = app(CommissionCalculator::class)->split($fare, $driver, $city);

    expect($split['commission']->minor + $split['driverEarnings']->minor)->toBe($fare->minor);
});
