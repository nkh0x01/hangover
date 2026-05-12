<?php

namespace App\Domain\Pricing;

use App\Domain\Availability\Period;
use App\Models\RoomType;
use Carbon\CarbonImmutable;

class PricingService
{
    /**
     * Phase 1 weekend uplift: Friday and Saturday nights cost 15% more.
     * The factor is intentionally hardcoded for now; in Phase 3 it becomes
     * a row in pricing_rules and the engine walks rules in priority order.
     */
    public const WEEKEND_UPLIFT_FACTOR = 1.15;

    public function priceForNight(RoomType $roomType, CarbonImmutable|\DateTimeInterface|string $date): NightlyRate
    {
        $date = $date instanceof CarbonImmutable
            ? $date
            : CarbonImmutable::parse((string) ($date instanceof \DateTimeInterface ? $date->format('Y-m-d') : $date));

        $base = (float) $roomType->base_price;
        $isWeekend = in_array($date->dayOfWeekIso, [5, 6], true); // Fri, Sat
        $amount = $isWeekend
            ? round($base * self::WEEKEND_UPLIFT_FACTOR, 2)
            : round($base, 2);

        return new NightlyRate(
            date: $date->startOfDay(),
            amount: $amount,
            currency: $roomType->property->base_currency,
            weekendUplift: $isWeekend,
        );
    }

    public function priceForStay(RoomType $roomType, Period $period): StayQuote
    {
        $nights = [];
        foreach ($period->nights() as $date) {
            $nights[] = $this->priceForNight($roomType, $date);
        }

        return new StayQuote(
            nights: $nights,
            currency: $roomType->property->base_currency,
        );
    }
}
