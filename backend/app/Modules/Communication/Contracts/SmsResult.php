<?php

declare(strict_types=1);

namespace App\Modules\Communication\Contracts;

final readonly class SmsResult
{
    public function __construct(
        public bool $sent,
        public ?string $providerMessageId,
        public ?float $cost = null,
        public ?string $error = null,
    ) {}

    public static function ok(?string $id = null, ?float $cost = null): self
    {
        return new self(true, $id, $cost, null);
    }

    public static function failure(string $error, ?string $providerId = null): self
    {
        return new self(false, $providerId, null, $error);
    }
}
