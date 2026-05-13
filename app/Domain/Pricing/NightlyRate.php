<?php

namespace App\Domain\Pricing;

use Carbon\CarbonImmutable;

/**
 * One night's priced result + the audit trail of how the engine got there.
 *   - basePrice: room_type.base_price (or the manual override if used)
 *   - applied:   list of [name, from, to, delta] entries, one per rule that fired
 *   - manualOverride: true if a daily_room_prices row replaced the base entirely
 *                     (in which case no rules fired)
 *
 * The UI renders this verbatim as a per-line breakdown so receptionists
 * can answer "why is tonight more expensive?" without leaving the page.
 */
final class NightlyRate
{
    /**
     * @param array<int, array{name: string, from: float, to: float, delta: float}> $applied
     */
    public function __construct(
        public readonly CarbonImmutable $date,
        public readonly float $amount,
        public readonly string $currency,
        public readonly bool $weekendUplift = false,
        public readonly float $basePrice = 0.0,
        public readonly array $applied = [],
        public readonly bool $manualOverride = false,
    ) {
    }

    public function toArray(): array
    {
        return [
            'date'            => $this->date->toDateString(),
            'amount'          => $this->amount,
            'currency'        => $this->currency,
            'weekend_uplift'  => $this->weekendUplift,
            'base_price'      => $this->basePrice,
            'manual_override' => $this->manualOverride,
            'applied'         => $this->applied,
        ];
    }
}
