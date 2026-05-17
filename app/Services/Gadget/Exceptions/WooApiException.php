<?php

namespace App\Services\Gadget\Exceptions;

use RuntimeException;

class WooApiException extends RuntimeException
{
    public function __construct(string $message, public readonly int $status = 0, public readonly array $body = [])
    {
        parent::__construct($message, $status);
    }
}
