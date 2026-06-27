<?php

declare(strict_types=1);

namespace App\Modules\Erp\Inventory\Exceptions;

use App\Support\Exceptions\DomainException;

final class InsufficientStockException extends DomainException
{
    public static function for(int $variantId, int $branchId, int $available, int $requested): self
    {
        return new self(
            sprintf('Insufficient stock for variant %d at branch %d: %d available, %d requested.', $variantId, $branchId, $available, $requested),
            ['variant_id' => $variantId, 'branch_id' => $branchId, 'available' => $available, 'requested' => $requested],
        );
    }

    public function code(): string
    {
        return 'inventory.insufficient_stock';
    }

    public function status(): int
    {
        return 422;
    }
}
