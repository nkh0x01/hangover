<?php

declare(strict_types=1);

namespace App\Modules\Identity\Exceptions;

use App\Support\Exceptions\DomainException;

final class InvalidOtpException extends DomainException
{
    public function code(): string
    {
        return 'auth.invalid_otp';
    }

    public function status(): int
    {
        return 422;
    }
}
