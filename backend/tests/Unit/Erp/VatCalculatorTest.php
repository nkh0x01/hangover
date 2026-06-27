<?php

declare(strict_types=1);

use App\Modules\Erp\Pos\Services\VatCalculator;

it('extracts 18% VAT from a VAT-inclusive gross', function (): void {
    // 118.00 inclusive -> 18.00 VAT
    expect(VatCalculator::extract(118.00, true))->toBe(18.00);
});

it('returns zero for VAT-exempt items', function (): void {
    expect(VatCalculator::extract(118.00, false))->toBe(0.0);
});

it('rounds the extracted VAT to two decimals', function (): void {
    // 100 * 18 / 118 = 15.2542... -> 15.25
    expect(VatCalculator::extract(100.00, true))->toBe(15.25);
});
