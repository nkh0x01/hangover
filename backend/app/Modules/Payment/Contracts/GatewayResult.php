<?php

declare(strict_types=1);

namespace App\Modules\Payment\Contracts;

final readonly class GatewayResult
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public bool $ok,
        public string $status,
        public ?string $providerIntentId,
        public ?string $failureCode = null,
        public array $raw = [],
    ) {}
}
