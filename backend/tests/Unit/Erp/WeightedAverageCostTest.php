<?php

declare(strict_types=1);

use App\Modules\Erp\Inventory\Services\WeightedAverageCost;

it('uses the received cost when nothing is on hand', function (): void {
    expect(WeightedAverageCost::next(0, 0.0, 10, 25.00))->toBe(25.00);
});

it('blends on-hand and received cost by quantity', function (): void {
    // 10 @ 20.00 + 10 @ 30.00 = 25.00 average
    expect(WeightedAverageCost::next(10, 20.00, 10, 30.00))->toBe(25.00);
});

it('weights toward the larger quantity', function (): void {
    // 90 @ 10.00 + 10 @ 20.00 = 11.00 average
    expect(WeightedAverageCost::next(90, 10.00, 10, 20.00))->toBe(11.00);
});

it('rounds to two decimals', function (): void {
    // (1*10 + 2*11)/3 = 10.6667 -> 10.67
    expect(WeightedAverageCost::next(1, 10.00, 2, 11.00))->toBe(10.67);
});
