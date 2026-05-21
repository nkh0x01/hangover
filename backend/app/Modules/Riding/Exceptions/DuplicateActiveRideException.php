<?php

declare(strict_types=1);

namespace App\Modules\Riding\Exceptions;

use App\Support\Exceptions\DomainException;

final class DuplicateActiveRideException extends DomainException
{
    public function code(): string
    {
        return 'ride.duplicate_active';
    }

    public function status(): int
    {
        return 409;
    }
}
