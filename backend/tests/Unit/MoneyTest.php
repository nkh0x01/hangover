<?php

declare(strict_types=1);

use App\Support\Money;

it('builds money from a decimal value', function (): void {
    $m = Money::fromDecimal('12.34', 'GEL');

    expect($m->minor)->toBe(1234)
        ->and($m->currency)->toBe('GEL')
        ->and($m->toDecimal())->toBe(12.34);
});

it('adds amounts of the same currency', function (): void {
    $sum = Money::fromDecimal('1.50', 'GEL')->add(Money::fromDecimal('2.50', 'GEL'));

    expect($sum->minor)->toBe(400);
});

it('refuses cross-currency arithmetic', function (): void {
    expect(fn () => Money::fromDecimal('1', 'GEL')->add(Money::fromDecimal('1', 'USD')))
        ->toThrow(InvalidArgumentException::class);
});
