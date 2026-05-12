<?php

declare(strict_types=1);

namespace App\Modules\Riding\Exceptions;

use App\Support\Exceptions\DomainException;

final class RideNotOfferableException extends DomainException
{
    public function code(): string
    {
        return 'ride.not_offerable';
    }
}
