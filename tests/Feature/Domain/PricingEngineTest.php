<?php

use App\Domain\Availability\Period;
use App\Domain\Pricing\PricingService;
use App\Models\DailyRoomPrice;
use App\Models\PricingRule;
use App\Models\Property;
use App\Models\RoomType;

beforeEach(function () {
    $this->property = Property::factory()->create(['base_currency' => 'USD']);
    $this->type = RoomType::factory()->create([
        'property_id' => $this->property->id,
        'base_price'  => 100,
    ]);
    $this->pricing = new PricingService();
});

function seedRule(int $propertyId, string $type, array $cond, array $action, int $priority = 100, ?int $roomTypeId = null, ?string $from = null, ?string $to = null): PricingRule
{
    return PricingRule::create([
        'property_id' => $propertyId,
        'name' => "Rule {$type}",
        'type' => $type,
        'priority' => $priority,
        'scope' => $roomTypeId ? PricingRule::SCOPE_ROOM_TYPE : PricingRule::SCOPE_PROPERTY,
        'room_type_id' => $roomTypeId,
        'conditions' => $cond,
        'action' => $action,
        'valid_from' => $from,
        'valid_to' => $to,
        'active' => true,
    ]);
}

it('returns base price with no rules and no overrides', function () {
    $rate = $this->pricing->priceForNight($this->type, '2026-06-01'); // Monday
    expect($rate->amount)->toBe(100.0);
});

it('weekend rule adds 15% on Fri/Sat only', function () {
    seedRule($this->property->id, PricingRule::TYPE_WEEKEND, ['days' => [5, 6]], ['type' => 'percent', 'value' => 15]);

    expect($this->pricing->priceForNight($this->type, '2026-06-05')->amount)->toBe(115.0) // Fri
        ->and($this->pricing->priceForNight($this->type, '2026-06-06')->amount)->toBe(115.0) // Sat
        ->and($this->pricing->priceForNight($this->type, '2026-06-07')->amount)->toBe(100.0); // Sun
});

it('seasonal rule applies within valid_from..valid_to only', function () {
    seedRule($this->property->id, PricingRule::TYPE_SEASONAL, [], ['type' => 'percent', 'value' => 25],
        priority: 100, from: '2026-07-01', to: '2026-08-31');

    expect($this->pricing->priceForNight($this->type, '2026-06-30')->amount)->toBe(100.0) // before
        ->and($this->pricing->priceForNight($this->type, '2026-07-15')->amount)->toBe(125.0) // inside
        ->and($this->pricing->priceForNight($this->type, '2026-09-01')->amount)->toBe(100.0); // after
});

it('holiday rule sets an absolute price on listed dates', function () {
    seedRule($this->property->id, PricingRule::TYPE_HOLIDAY,
        ['dates' => ['2026-12-31', '2027-01-01']],
        ['type' => 'set', 'value' => 350]);

    expect($this->pricing->priceForNight($this->type, '2026-12-31')->amount)->toBe(350.0)
        ->and($this->pricing->priceForNight($this->type, '2026-12-30')->amount)->toBe(100.0);
});

it('last-minute rule discounts when arrival is close', function () {
    // Today; daysToArrival = 0
    seedRule($this->property->id, PricingRule::TYPE_LAST_MINUTE,
        ['max_days_to_arrival' => 3],
        ['type' => 'percent', 'value' => -10]);

    $todayMonday = \Carbon\CarbonImmutable::today()->next(\Carbon\CarbonInterface::MONDAY)->toDateString();
    // Use today (any weekday).
    $today = \Carbon\CarbonImmutable::today()->toDateString();
    expect($this->pricing->priceForNight($this->type, $today)->amount)->toBe(90.0);
});

it('length-of-stay rule applies a discount when stay is long', function () {
    seedRule($this->property->id, PricingRule::TYPE_LENGTH_OF_STAY,
        ['min_nights' => 7],
        ['type' => 'percent', 'value' => -10]);

    // 7-night stay
    $period = new Period('2026-06-01', '2026-06-08');
    $quote = $this->pricing->priceForStay($this->type, $period);
    // 7 × 90.0 = 630
    expect($quote->total())->toBe(7 * 90.0);
});

it('manual daily override wins absolutely (rules are bypassed)', function () {
    seedRule($this->property->id, PricingRule::TYPE_WEEKEND, ['days' => [5, 6]], ['type' => 'percent', 'value' => 15]);

    DailyRoomPrice::create([
        'property_id'  => $this->property->id,
        'room_type_id' => $this->type->id,
        'room_id'      => null,
        'date'         => '2026-06-06', // Saturday — weekend rule would normally fire
        'price'        => 250,
        'source'       => DailyRoomPrice::SOURCE_MANUAL,
    ]);

    expect($this->pricing->priceForNight($this->type, '2026-06-06')->amount)->toBe(250.0);
});

it('multiple rules compose by priority order', function () {
    // weekend +15% (priority 100), then seasonal -10% (priority 200)
    seedRule($this->property->id, PricingRule::TYPE_WEEKEND, ['days' => [5, 6]], ['type' => 'percent', 'value' => 15], 100);
    seedRule($this->property->id, PricingRule::TYPE_SEASONAL, [], ['type' => 'percent', 'value' => -10], 200,
        from: '2026-06-01', to: '2026-06-30');

    // Saturday in season: 100 * 1.15 = 115, then 115 * 0.9 = 103.5
    $rate = $this->pricing->priceForNight($this->type, '2026-06-06');
    expect($rate->amount)->toBe(103.5);
});

it('inactive rules are ignored', function () {
    $r = seedRule($this->property->id, PricingRule::TYPE_WEEKEND, ['days' => [5, 6]], ['type' => 'percent', 'value' => 15]);
    $r->update(['active' => false]);

    expect($this->pricing->priceForNight($this->type, '2026-06-06')->amount)->toBe(100.0);
});

it('restrictionsForStay rolls up min_stay across the period', function () {
    DailyRoomPrice::create([
        'property_id'  => $this->property->id,
        'room_type_id' => $this->type->id,
        'date'         => '2026-06-05',
        'min_stay'     => 3,
        'source'       => DailyRoomPrice::SOURCE_MANUAL,
    ]);

    $r = $this->pricing->restrictionsForStay($this->type, new Period('2026-06-05', '2026-06-06'));
    expect($r->violatedBy(new Period('2026-06-05', '2026-06-06')))->not->toBeNull()
        ->and($r->violatedBy(new Period('2026-06-05', '2026-06-08')))->toBeNull();
});

it('restrictionsForStay flags CTA and CTD', function () {
    DailyRoomPrice::create([
        'property_id' => $this->property->id,
        'room_type_id' => $this->type->id,
        'date' => '2026-06-10',
        'closed_to_arrival' => true,
        'source' => DailyRoomPrice::SOURCE_MANUAL,
    ]);
    DailyRoomPrice::create([
        'property_id' => $this->property->id,
        'room_type_id' => $this->type->id,
        'date' => '2026-06-13',
        'closed_to_departure' => true,
        'source' => DailyRoomPrice::SOURCE_MANUAL,
    ]);

    $r = $this->pricing->restrictionsForStay($this->type, new Period('2026-06-10', '2026-06-12'));
    expect($r->violatedBy(new Period('2026-06-10', '2026-06-12')))->not->toBeNull();

    $r2 = $this->pricing->restrictionsForStay($this->type, new Period('2026-06-11', '2026-06-13'));
    expect($r2->violatedBy(new Period('2026-06-11', '2026-06-13')))->not->toBeNull();

    // A stay that arrives and departs outside the closed days passes.
    $r3 = $this->pricing->restrictionsForStay($this->type, new Period('2026-06-14', '2026-06-16'));
    expect($r3->violatedBy(new Period('2026-06-14', '2026-06-16')))->toBeNull();
});
