<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Services;

use App\Modules\Geo\Models\City;
use App\Modules\Identity\Models\User;
use App\Modules\Pricing\Models\FareEstimate;
use App\Modules\Pricing\Models\FareRule;
use App\Support\Geo\Point;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * Phase 1.5 fare engine — straight-line Haversine distance, average-speed
 * duration heuristic, FareRule for the city + vehicle type. Surge hooks
 * exist but are flat 1.0 until Phase 2 adds the surge calculator.
 *
 * The result is persisted as a FareEstimate row so the customer's
 * subsequent /rides POST can lock the quoted price by ulid — no
 * recomputation, no drift if pricing changes mid-flow.
 */
final class FareEstimateService
{
    private const AVERAGE_SCOOTER_KMH = 25.0;
    private const DETOUR_FACTOR = 1.35;

    public function estimate(
        User $customer,
        City $city,
        Point $pickup,
        Point $dropoff,
        string $vehicleType = 'scooter_electric',
    ): FareEstimate {
        $rule = $this->resolveRule($city, $vehicleType);

        // Straight-line Haversine, then apply a detour factor — close
        // enough for an MVP fare lock; Phase 2 will swap in MapProvider
        // routing once the concrete provider is wired.
        $straightKm = $pickup->distanceTo($dropoff) / 1000;
        $distanceKm = round($straightKm * self::DETOUR_FACTOR, 3);
        $durationMin = (int) max(1, ceil(($distanceKm / self::AVERAGE_SCOOTER_KMH) * 60));

        $surgeMultiplier = $this->resolveSurge($city);

        $rawFare =
            (float) $rule->base_fare
            + (float) $rule->price_per_km * $distanceKm
            + (float) $rule->price_per_min * $durationMin
            + (float) $rule->booking_fee;

        $afterSurge = $rawFare * $surgeMultiplier;
        $total = max((float) $rule->minimum_fare, $afterSurge);

        $caps = (array) config('pricing.caps', []);
        $total = min((float) ($caps['max_fare'] ?? 500.0), $total);
        $total = max((float) ($caps['min_fare'] ?? 1.0), $total);

        return FareEstimate::create([
            'customer_id' => $customer->id,
            'city_id' => $city->id,
            'pickup_lat' => $pickup->lat,
            'pickup_lng' => $pickup->lng,
            'dropoff_lat' => $dropoff->lat,
            'dropoff_lng' => $dropoff->lng,
            'distance_km' => $distanceKm,
            'duration_min' => $durationMin,
            'base_fare' => $rule->base_fare,
            'surge_multiplier' => $surgeMultiplier,
            'total_amount' => round($total, 2),
            'currency' => $city->default_currency,
            'expires_at' => CarbonImmutable::now()->addMinutes(
                (int) config('pricing.fare_estimate_ttl_minutes', 30),
            ),
        ]);
    }

    private function resolveRule(City $city, string $vehicleType): FareRule
    {
        $rule = FareRule::query()
            ->where('city_id', $city->id)
            ->where('vehicle_type', $vehicleType)
            ->where('active_from', '<=', now())
            ->where(function ($q): void {
                $q->whereNull('active_until')->orWhere('active_until', '>', now());
            })
            ->orderByDesc('active_from')
            ->first();

        if (! $rule) {
            throw (new ModelNotFoundException)->setModel(FareRule::class);
        }

        return $rule;
    }

    private function resolveSurge(City $city): float
    {
        // Phase 2 will look up surge_multipliers by zone; for now hold
        // at 1.0 unless an admin sets pricing.default_surge_multiplier.
        $cap = (float) (config('pricing.caps.max_surge_multiplier') ?? 5.0);
        $value = (float) (config('pricing.default_surge_multiplier', 1.0));

        return min($cap, max(1.0, $value));
    }
}
