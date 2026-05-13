<?php

declare(strict_types=1);

use App\Support\Money;

it('rounds decimals to integer minor units', function (): void {
    $m = Money::fromDecimal('7.55', 'GEL');
    expect($m->minor)->toBe(755);
    expect($m->toDecimal())->toBe(7.55);
});

it('handles bank-rounding edge cases without drift', function (): void {
    // 0.1 + 0.2 is 0.30000000000000004 as float — must round to 30.
    $m = Money::fromDecimal(0.1 + 0.2, 'GEL');
    expect($m->minor)->toBe(30);
});

it('multiplies + subtracts preserving currency', function (): void {
    $fare = Money::fromDecimal('20.00', 'GEL');
    $commission = $fare->multiply(0.15);
    $driver = $fare->subtract($commission);

    expect($commission->minor)->toBe(300);
    expect($driver->minor)->toBe(1700);
    expect($commission->currency)->toBe('GEL');
});

it('rejects currency mismatch', function (): void {
    $gel = Money::fromDecimal('5.00', 'GEL');
    $usd = Money::fromDecimal('5.00', 'USD');

    expect(fn () => $gel->add($usd))->toThrow(InvalidArgumentException::class);
});
