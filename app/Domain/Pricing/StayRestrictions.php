<?php

namespace App\Domain\Pricing;

use App\Domain\Availability\Period;

/**
 * Per-stay roll-up of the restriction columns on daily_room_prices.
 * Built by PricingService::priceForStay; consumed by CreateReservation
 * to refuse stays that violate the rules.
 */
final class StayRestrictions
{
    public function __construct(
        public readonly int $maxMinStay,
        public readonly ?int $minMaxStay,
        public readonly bool $arrivalClosed,
        public readonly bool $departureClosed,
    ) {
    }

    public function violatedBy(Period $period): ?string
    {
        $nights = $period->nightCount();

        if ($this->maxMinStay > 0 && $nights < $this->maxMinStay) {
            return "Minimum stay is {$this->maxMinStay} night(s).";
        }
        if ($this->minMaxStay !== null && $nights > $this->minMaxStay) {
            return "Maximum stay is {$this->minMaxStay} night(s).";
        }
        if ($this->arrivalClosed) {
            return "Check-in is closed on {$period->checkIn->toDateString()}.";
        }
        if ($this->departureClosed) {
            return "Check-out is closed on {$period->checkOut->toDateString()}.";
        }

        return null;
    }
}
