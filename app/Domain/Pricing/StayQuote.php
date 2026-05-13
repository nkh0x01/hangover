<?php

namespace App\Domain\Pricing;

final class StayQuote
{
    /**
     * @param list<NightlyRate> $nights
     */
    public function __construct(
        public readonly array $nights,
        public readonly string $currency,
    ) {
    }

    public function total(): float
    {
        return array_reduce(
            $this->nights,
            fn (float $carry, NightlyRate $n) => $carry + $n->amount,
            0.0,
        );
    }

    public function nightCount(): int
    {
        return count($this->nights);
    }
}
