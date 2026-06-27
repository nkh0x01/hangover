<?php

declare(strict_types=1);

namespace App\Modules\Erp\Integration;

use App\Modules\Erp\Integration\Contracts\IntegrationLogger;
use App\Modules\Erp\Integration\Models\IntegrationLog;
use App\Support\Ulid;

final class DatabaseIntegrationLogger implements IntegrationLogger
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
    ): void {
        IntegrationLog::create([
            'ulid' => Ulid::new(),
            'provider' => $provider,
            'operation' => $operation,
            'request' => $request,
            'response' => $response,
            'success' => $success,
            'verified' => $verified,
            'idempotency_key' => $idempotencyKey,
            'reference' => $reference,
            'error' => $error,
        ]);
    }
}
