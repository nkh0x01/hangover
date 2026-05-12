<?php

namespace App\Domain\Pricing;

use Carbon\CarbonImmutable;

final class NightlyRate
{
    public function __construct(
        public readonly CarbonImmutable $date,
        public readonly float $amount,
        public readonly string $currency,
        public readonly bool $weekendUplift = false,
    ) {
    }

    public function toArray(): array
    {
        return [
            'date'            => $this->date->toDateString(),
            'amount'          => $this->amount,
            'currency'        => $this->currency,
            'weekend_uplift'  => $this->weekendUplift,
        ];
    }
}
