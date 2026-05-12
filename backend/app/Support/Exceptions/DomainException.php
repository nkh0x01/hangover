<?php

declare(strict_types=1);

namespace App\Support\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Base for business-rule violations. Each subclass declares a stable
 * machine code (snake.dot.case) and an HTTP status that the global
 * exception handler emits without further mapping.
 */
abstract class DomainException extends RuntimeException
{
    /**
     * @param array<string, mixed> $details
     */
    public function __construct(
        string $message = '',
        public readonly array $details = [],
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    abstract public function code(): string;

    public function status(): int
    {
        return 409;
    }
}
