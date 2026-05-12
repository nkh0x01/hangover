<?php

use App\Domain\Availability\Period;
use App\Domain\Pricing\PricingService;
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

it('returns base price for a weekday night', function () {
    // Monday 2026-05-11
    $rate = $this->pricing->priceForNight($this->type, '2026-05-11');
    expect($rate->amount)->toBe(100.0)
        ->and($rate->weekendUplift)->toBeFalse();
});

it('applies 15% uplift on Friday and Saturday', function () {
    // 2026-05-15 = Friday
    $fri = $this->pricing->priceForNight($this->type, '2026-05-15');
    // 2026-05-16 = Saturday
    $sat = $this->pricing->priceForNight($this->type, '2026-05-16');

    expect($fri->weekendUplift)->toBeTrue()
        ->and($fri->amount)->toBe(115.0)
        ->and($sat->weekendUplift)->toBeTrue()
        ->and($sat->amount)->toBe(115.0);
});

it('Sunday is not weekend-uplifted', function () {
    // 2026-05-17 = Sunday
    $sun = $this->pricing->priceForNight($this->type, '2026-05-17');
    expect($sun->weekendUplift)->toBeFalse()
        ->and($sun->amount)->toBe(100.0);
});

it('sums the stay quote correctly across a weekend', function () {
    // Thu → Mon = 4 nights: Thu (100) + Fri (115) + Sat (115) + Sun (100) = 430
    $period = new Period('2026-05-14', '2026-05-18');
    $quote = $this->pricing->priceForStay($this->type, $period);

    expect($quote->nightCount())->toBe(4)
        ->and($quote->total())->toBe(430.0)
        ->and($quote->currency)->toBe('USD');
});
