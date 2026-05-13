<?php

namespace App\Domain\Pricing;

use App\Models\RoomType;
use Carbon\CarbonImmutable;

/**
 * Input to a pricing rule's applies() / apply() method. Pre-computed so
 * each rule stays a pure function.
 */
final class PricingContext
{
    public function __construct(
        public readonly RoomType $roomType,
        public readonly CarbonImmutable $date,
        public readonly int $stayLength,
        public readonly int $daysToArrival,
        public readonly float $occupancyPercent,
    ) {
    }
}
