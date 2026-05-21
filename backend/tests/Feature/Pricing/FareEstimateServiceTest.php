<?php

declare(strict_types=1);

use App\Modules\Geo\Models\City;
use App\Modules\Identity\Models\User;
use App\Modules\Pricing\Models\FareRule;
use App\Modules\Pricing\Services\FareEstimateService;
use App\Support\Geo\Point;

beforeEach(function (): void {
    $this->city = City::factory()->create(['default_currency' => 'GEL']);
    FareRule::create([
        'city_id' => $this->city->id,
        'vehicle_type' => 'scooter_electric',
        'name' => 'default',
        'base_fare' => 2.50,
        'price_per_km' => 1.20,
        'price_per_min' => 0.15,
        'minimum_fare' => 4.00,
        'booking_fee' => 0.50,
        'commission_rate' => 0.20,
        'free_waiting_minutes' => 3,
        'waiting_fee_per_min' => 0.20,
        'cancellation_fee' => 2.00,
        'active_from' => now()->subDay(),
        'active_until' => null,
        'day_of_week_mask' => 0x7F,
        'starts_at_local' => '00:00:00',
        'ends_at_local' => '23:59:59',
    ]);
    $this->customer = User::factory()->create();
    $this->service = app(FareEstimateService::class);
});

it('builds a fare estimate using straight-line distance and detour factor', function (): void {
    // ~2 km in central Tbilisi
    $pickup = new Point(41.7151, 44.8271);
    $dropoff = new Point(41.7325, 44.8271);

    $estimate = $this->service->estimate($this->customer, $this->city, $pickup, $dropoff);

    expect((float) $estimate->distance_km)->toBeGreaterThan(2.0)
        ->and((float) $estimate->distance_km)->toBeLessThan(3.5)
        ->and((float) $estimate->total_amount)->toBeGreaterThan(4.0)
        ->and($estimate->currency)->toBe('GEL')
        ->and($estimate->expires_at->isFuture())->toBeTrue();
});

it('enforces the minimum fare for very short rides', function (): void {
    $pickup = new Point(41.7151, 44.8271);
    $dropoff = new Point(41.7152, 44.8272);   // < 50m

    $estimate = $this->service->estimate($this->customer, $this->city, $pickup, $dropoff);

    expect((float) $estimate->total_amount)->toBeGreaterThanOrEqual(4.0);
});

it('persists the estimate with a public ulid the rides API can lock', function (): void {
    $estimate = $this->service->estimate(
        $this->customer,
        $this->city,
        new Point(41.7151, 44.8271),
        new Point(41.7321, 44.8271),
    );

    expect($estimate->ulid)->toBeString()
        ->and(strlen($estimate->ulid))->toBe(26);
});
