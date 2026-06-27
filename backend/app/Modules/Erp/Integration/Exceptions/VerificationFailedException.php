<?php

declare(strict_types=1);

namespace App\Modules\Erp\Integration\Exceptions;

use App\Support\Exceptions\DomainException;

/**
 * The remote reported success but the real data change could not be
 * verified — the silent-failure case. The operation is treated as failed
 * and must be retried/reconciled, never recorded as done.
 */
final class VerificationFailedException extends DomainException
{
    public static function for(string $provider, string $operation): self
    {
        return new self(
            sprintf('Integration [%s:%s] reported success but verification failed.', $provider, $operation),
            ['provider' => $provider, 'operation' => $operation],
        );
    }

    public function code(): string
    {
        return 'integration.verification_failed';
    }

    public function status(): int
    {
        return 502;
    }
}
