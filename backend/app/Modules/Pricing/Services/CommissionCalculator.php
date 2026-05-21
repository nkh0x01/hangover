<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Services;

use App\Modules\Driver\Models\Driver;
use App\Modules\Geo\Models\City;
use App\Support\Money;

/**
 * Single source of truth for "how much does the platform keep from
 * this ride". Reads from {@see config('commission')} + the driver's
 * own override.
 *
 * Resolution order (first hit wins):
 *   1. `drivers.commission_rate_override`  (non-null)
 *   2. `config('commission.by_city.<CityCode>')` (when present)
 *   3. `config('commission.default_rate')`
 *
 * Always clamped between `min_amount` and `max_amount` so a bad fare
 * input can't ever yield a wildly off commission. Caller is expected
 * to pass an already-validated fare in the ledger currency.
 */
final class CommissionCalculator
{
    /**
     * @return array{commission: Money, driverEarnings: Money, rate: float}
     */
    public function split(Money $fare, Driver $driver, ?City $city = null): array
    {
        $rate = $this->resolveRate($driver, $city);

        $commission = $fare->multiply($rate);
        $commission = $this->clamp($commission, $fare->currency);

        // If the clamp shaved the commission, the driver gets the
        // remainder — invariant `fare = commission + driverEarnings`.
        $driverEarnings = $fare->subtract($commission);

        return [
            'commission' => $commission,
            'driverEarnings' => $driverEarnings,
            'rate' => $rate,
        ];
    }

    public function resolveRate(Driver $driver, ?City $city = null): float
    {
        if ($driver->commission_rate_override !== null) {
            return (float) $driver->commission_rate_override;
        }

        if ($city !== null) {
            $byCity = (array) config('commission.by_city', []);
            $slug = (string) $city->slug;
            if ($slug !== '' && isset($byCity[$slug])) {
                return (float) $byCity[$slug];
            }
            if ($city->default_commission_rate !== null) {
                return (float) $city->default_commission_rate;
            }
        }

        return (float) config('commission.default_rate', 0.15);
    }

    private function clamp(Money $commission, string $currency): Money
    {
        $minMinor = (int) round(((float) config('commission.min_amount', 0)) * 100);
        $maxMinor = (int) round(((float) config('commission.max_amount', PHP_INT_MAX)) * 100);

        if ($commission->minor < $minMinor) {
            return new Money($minMinor, $currency);
        }
        if ($commission->minor > $maxMinor) {
            return new Money($maxMinor, $currency);
        }

        return $commission;
    }
}
