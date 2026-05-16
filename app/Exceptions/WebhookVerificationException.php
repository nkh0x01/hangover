<?php

namespace App\Exceptions;

use RuntimeException;

class WebhookVerificationException extends RuntimeException
{
    public function __construct(string $reason = 'invalid_signature')
    {
        parent::__construct($reason, 403);
    }
}
