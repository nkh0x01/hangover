<?php

declare(strict_types=1);

namespace App\Modules\Identity\Exceptions;

use App\Support\Exceptions\DomainException;

final class OtpThrottledException extends DomainException
{
    public function code(): string
    {
        return 'auth.otp_throttled';
    }

    public function status(): int
    {
        return 429;
    }
}
