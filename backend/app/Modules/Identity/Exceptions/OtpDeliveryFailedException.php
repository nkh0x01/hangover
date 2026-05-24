<?php

declare(strict_types=1);

namespace App\Modules\Identity\Exceptions;

use App\Support\Exceptions\DomainException;

final class OtpDeliveryFailedException extends DomainException
{
    public function code(): string
    {
        return 'auth.otp_delivery_failed';
    }

    public function status(): int
    {
        return 502;
    }
}
