<?php

declare(strict_types=1);

namespace App\Support;

use Symfony\Component\Uid\Ulid as SymfonyUlid;

/**
 * Thin wrapper over Symfony's Ulid so the rest of the codebase never
 * touches an external namespace directly.
 */
final class Ulid
{
    public static function new(): string
    {
        return (string) new SymfonyUlid;
    }

    public static function isValid(string $value): bool
    {
        return SymfonyUlid::isValid($value);
    }
}
