<?php

namespace App\Domain\Availability;

use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use InvalidArgumentException;

/**
 * Half-open date interval [checkIn, checkOut).
 *
 * A reservation from 2026-05-10 to 2026-05-12 occupies the NIGHTS of
 * 2026-05-10 and 2026-05-11. The 12th is the departure day and another
 * guest can arrive that same day. All availability and pricing logic
 * depends on this convention.
 */
final class Period
{
    public readonly CarbonImmutable $checkIn;

    public readonly CarbonImmutable $checkOut;

    public function __construct(string|\DateTimeInterface $checkIn, string|\DateTimeInterface $checkOut)
    {
        $this->checkIn  = CarbonImmutable::parse($checkIn)->startOfDay();
        $this->checkOut = CarbonImmutable::parse($checkOut)->startOfDay();

        if ($this->checkOut->lessThanOrEqualTo($this->checkIn)) {
            throw new InvalidArgumentException(
                'Check-out date must be strictly after check-in date.',
            );
        }
    }

    public function nightCount(): int
    {
        return (int) $this->checkIn->diffInDays($this->checkOut);
    }

    /**
     * @return list<CarbonImmutable> one entry per occupied night
     */
    public function nights(): array
    {
        $nights = [];
        foreach (CarbonPeriod::create($this->checkIn, $this->checkOut->subDay()) as $date) {
            $nights[] = CarbonImmutable::instance($date)->startOfDay();
        }

        return $nights;
    }

    /**
     * @return list<string> Y-m-d strings, useful for whereIn() queries.
     */
    public function nightDates(): array
    {
        return array_map(fn (CarbonImmutable $d) => $d->toDateString(), $this->nights());
    }

    public function overlaps(self $other): bool
    {
        return $this->checkIn->lessThan($other->checkOut)
            && $other->checkIn->lessThan($this->checkOut);
    }

    public function equals(self $other): bool
    {
        return $this->checkIn->equalTo($other->checkIn)
            && $this->checkOut->equalTo($other->checkOut);
    }

    public function __toString(): string
    {
        return $this->checkIn->toDateString().' → '.$this->checkOut->toDateString();
    }
}
