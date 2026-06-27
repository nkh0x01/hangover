<?php

declare(strict_types=1);

namespace App\Modules\Erp\Integration\Contracts;

/**
 * Persists an audit row for every integration attempt. Kept behind an
 * interface so the verification logic in AbstractGateway is testable
 * without a database.
 */
interface IntegrationLogger
{
    /**
     * @param array<string, mixed> $request
     * @param array<string, mixed> $response
     */
    public function log(
        string $provider,
        string $operation,
        array $request,
        array $response,
        bool $success,
        bool $verified,
        ?string $idempotencyKey = null,
        ?string $reference = null,
        ?string $error = null,
    ): void;
}
