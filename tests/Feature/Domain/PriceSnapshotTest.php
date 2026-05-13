<?php

use App\Domain\Availability\Period;
use App\Domain\Pricing\PricingService;
use App\Domain\Reservations\Actions\CreateReservation;
use App\Domain\Reservations\Actions\MoveReservation;
use App\Domain\Reservations\Data\CreateReservationData;
use App\Models\PricingRule;
use Tests\Support\PmsTestFactory;

beforeEach(function () {
    $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    $this->p = new PmsTestFactory();
});

it('quote returns a per-night breakdown showing every rule that fired', function () {
    PricingRule::create([
        'property_id' => $this->p->property->id, 'name' => 'Weekend',
        'type' => PricingRule::TYPE_WEEKEND, 'priority' => 100,
        'scope' => PricingRule::SCOPE_PROPERTY,
        'conditions' => ['days' => [5, 6]],
        'action' => ['type' => 'percent', 'value' => 15],
        'active' => true,
    ]);
    PricingRule::create([
        'property_id' => $this->p->property->id, 'name' => 'Summer season',
        'type' => PricingRule::TYPE_SEASONAL, 'priority' => 200,
        'scope' => PricingRule::SCOPE_PROPERTY,
        'conditions' => [],
        'action' => ['type' => 'percent', 'value' => 25],
        'valid_from' => '2026-06-01', 'valid_to' => '2026-08-31',
        'active' => true,
    ]);

    $quote = app(PricingService::class)->priceForStay(
        $this->p->standardType,
        new Period('2026-06-05', '2026-06-08'), // Fri + Sat + Sun (3 nights, in season)
    );

    $fri = $quote->nights[0];
    expect($fri->basePrice)->toBe(100.0);

    $names = array_column($fri->applied, 'name');
    expect($names)->toContain('Weekend')->toContain('Summer season')
        ->and($fri->amount)->toBe(round(100 * 1.15 * 1.25, 2));

    // Sunday: only summer applies
    $sun = $quote->nights[2];
    expect(array_column($sun->applied, 'name'))->toBe(['Summer season'])
        ->and($sun->amount)->toBe(round(100 * 1.25, 2));
});

it('existing reservation keeps its price snapshot when rules change afterwards', function () {
    PricingRule::create([
        'property_id' => $this->p->property->id, 'name' => 'Weekend',
        'type' => PricingRule::TYPE_WEEKEND, 'priority' => 100,
        'scope' => PricingRule::SCOPE_PROPERTY,
        'conditions' => ['days' => [5, 6]],
        'action' => ['type' => 'percent', 'value' => 15],
        'active' => true,
    ]);

    $res = app(CreateReservation::class)->execute(new CreateReservationData(
        property: $this->p->property,
        guest:    $this->p->guest,
        roomType: $this->p->standardType,
        period:   new Period('2026-06-05', '2026-06-08'), // Fri,Sat,Sun
        room:     $this->p->room(0),
        adults:   1,
    ));

    $beforeRates = $res->nightsBreakdown
        ->sortBy('date')
        ->pluck('nightly_rate')
        ->map(fn ($v) => (float) $v)
        ->all();

    // Now jack the base price way up; the snapshot must NOT move.
    $this->p->standardType->update(['base_price' => 999]);

    $res->refresh()->load('nightsBreakdown');
    $afterRates = $res->nightsBreakdown
        ->sortBy('date')
        ->pluck('nightly_rate')
        ->map(fn ($v) => (float) $v)
        ->all();

    expect($afterRates)->toBe($beforeRates);
});

it('moving a reservation re-quotes at current pricing', function () {
    // No rules yet — base 100 everywhere.
    $res = app(CreateReservation::class)->execute(new CreateReservationData(
        property: $this->p->property,
        guest:    $this->p->guest,
        roomType: $this->p->standardType,
        period:   new Period('2026-06-01', '2026-06-04'),
        room:     $this->p->room(0),
        adults:   1,
    ));

    $originalTotal = (float) $res->fresh()->room_rate_total;
    expect($originalTotal)->toBe(300.0); // 3 × 100

    // Add a +50% rule before the move.
    PricingRule::create([
        'property_id' => $this->p->property->id, 'name' => 'Surge',
        'type' => PricingRule::TYPE_SEASONAL, 'priority' => 100,
        'scope' => PricingRule::SCOPE_PROPERTY,
        'conditions' => [],
        'action' => ['type' => 'percent', 'value' => 50],
        'valid_from' => '2026-07-01', 'valid_to' => '2026-07-31',
        'active' => true,
    ]);

    // Move into the surge window.
    app(MoveReservation::class)->execute(
        $res,
        new Period('2026-07-10', '2026-07-13'),
    );

    $newTotal = (float) $res->fresh()->room_rate_total;
    expect($newTotal)->toBe(450.0); // 3 × (100 × 1.50)
});
