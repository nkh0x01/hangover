<?php

declare(strict_types=1);

namespace App\Modules\Erp\Core\Scopes;

/**
 * Holds the active branch id for the current request/job. Set on login or
 * when a cashier opens a shift; left null for HQ/cross-branch reporting,
 * which makes BranchScope a no-op. Branch-owned writes stamp this value so
 * a row can never silently land in the wrong branch.
 */
final class BranchContext
{
    private static ?int $branchId = null;

    public static function set(?int $branchId): void
    {
        self::$branchId = $branchId;
    }

    public static function current(): ?int
    {
        return self::$branchId;
    }

    public static function clear(): void
    {
        self::$branchId = null;
    }

    /**
     * Run a callback scoped to a specific branch, restoring the previous
     * context afterwards. Used by jobs/reports that must pin one branch.
     *
     * @template T
     *
     * @param callable():T $callback
     * @return T
     */
    public static function for(?int $branchId, callable $callback): mixed
    {
        $previous = self::$branchId;
        self::$branchId = $branchId;

        try {
            return $callback();
        } finally {
            self::$branchId = $previous;
        }
    }
}
