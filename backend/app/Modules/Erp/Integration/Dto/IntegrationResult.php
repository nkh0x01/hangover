<?php

declare(strict_types=1);

namespace App\Modules\Erp\Integration\Dto;

/**
 * Outcome of an integration call. `success` is what the remote reported;
 * `verified` is whether we independently confirmed the real data change.
 * ok() requires BOTH — a truthy remote response is never trusted alone.
 */
final readonly class IntegrationResult
{
    /**
     * @param array<string, mixed> $response
     */
    public function __construct(
        public bool $success,
        public bool $verified,
        public array $response,
        public ?string $reference = null,
    ) {}

    public function ok(): bool
    {
        return $this->success && $this->verified;
    }
}
