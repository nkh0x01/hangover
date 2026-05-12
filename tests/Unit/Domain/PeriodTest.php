<?php

use App\Domain\Availability\Period;

it('counts nights inclusive of check-in and exclusive of check-out', function () {
    $p = new Period('2026-05-10', '2026-05-12');
    expect($p->nightCount())->toBe(2)
        ->and($p->nightDates())->toBe(['2026-05-10', '2026-05-11']);
});

it('rejects a non-positive period', function () {
    expect(fn () => new Period('2026-05-10', '2026-05-10'))
        ->toThrow(InvalidArgumentException::class);
    expect(fn () => new Period('2026-05-12', '2026-05-10'))
        ->toThrow(InvalidArgumentException::class);
});

it('overlap detection respects the half-open boundary', function () {
    $a = new Period('2026-05-10', '2026-05-12');
    $sameDay = new Period('2026-05-12', '2026-05-14');
    $inside  = new Period('2026-05-11', '2026-05-13');

    expect($a->overlaps($sameDay))->toBeFalse()
        ->and($a->overlaps($inside))->toBeTrue();
});

it('handles year and month boundaries correctly', function () {
    $p = new Period('2026-12-30', '2027-01-02');
    expect($p->nightCount())->toBe(3)
        ->and($p->nightDates())->toBe(['2026-12-30', '2026-12-31', '2027-01-01']);
});
