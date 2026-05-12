<?php

declare(strict_types=1);

namespace App\Modules\Communication\Contracts;

interface SmsGateway
{
    /**
     * Send an SMS. Implementations must be idempotent on (phone, purpose, code-hash)
     * within a 30-second window — the OTP layer relies on safe retries.
     */
    public function send(string $phoneE164, string $body, string $purpose): SmsResult;
}
