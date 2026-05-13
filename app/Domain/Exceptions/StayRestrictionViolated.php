<?php

namespace App\Domain\Exceptions;

class StayRestrictionViolated extends DomainException
{
    public static function from(string $reason): self
    {
        return new self($reason);
    }
}
