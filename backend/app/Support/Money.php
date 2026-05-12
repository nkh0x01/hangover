<?php

declare(strict_types=1);

namespace App\Support;

use InvalidArgumentException;

/**
 * Money value object. Amount is in the major currency unit with two
 * decimals stored as integer minor units to avoid float drift.
 */
final readonly class Money
{
    public function __construct(
        public int $minor,
        public string $currency,
    ) {
        if (strlen($currency) !== 3) {
            throw new InvalidArgumentException('Currency must be ISO-4217.');
        }
    }

    public static function fromDecimal(float|string $amount, string $currency): self
    {
        $minor = (int) round(((float) $amount) * 100);

        return new self($minor, strtoupper($currency));
    }

    public function toDecimal(): float
    {
        return $this->minor / 100;
    }

    public function add(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->minor + $other->minor, $this->currency);
    }

    public function subtract(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->minor - $other->minor, $this->currency);
    }

    public function multiply(float $factor): self
    {
        return new self((int) round($this->minor * $factor), $this->currency);
    }

    private function assertSameCurrency(self $other): void
    {
        if ($this->currency !== $other->currency) {
            throw new InvalidArgumentException('Currency mismatch.');
        }
    }
}
