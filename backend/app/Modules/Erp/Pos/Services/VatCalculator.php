<?php

declare(strict_types=1);

namespace App\Modules\Erp\Pos\Services;

/**
 * Georgian VAT. Retail prices are quoted VAT-inclusive, so the tax is
 * extracted from the gross rather than added on top. VAT-exempt SKUs return
 * zero. Pure arithmetic so it is unit-testable in isolation.
 */
final class VatCalculator
{
    public const RATE = 18;

    public static function extract(float $grossInclusive, bool $applicable): float
    {
        if (! $applicable) {
            return 0.0;
        }

        return round($grossInclusive * self::RATE / (100 + self::RATE), 2);
    }
}
