<?php

declare(strict_types=1);

namespace App\Modules\Erp\Integration\Exceptions;

use App\Support\Exceptions\DomainException;

/**
 * The remote call did not report success (transport error or an explicit
 * failure response).
 */
final class IntegrationFailedException extends DomainException
{
    public static function for(string $provider, string $operation, ?string $reason = null): self
    {
        return new self(
            sprintf('Integration [%s:%s] failed%s', $provider, $operation, $reason !== null ? ': '.$reason : '.'),
            ['provider' => $provider, 'operation' => $operation, 'reason' => $reason],
        );
    }

    public function code(): string
    {
        return 'integration.failed';
    }

    public function status(): int
    {
        return 502;
    }
}
