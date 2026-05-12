<?php

namespace App\Domain\Inventory\Actions;

use App\Domain\Inventory\InventoryService;
use App\Models\InventoryLocation;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\User;

/**
 * Loss / damage / arbitrary adjustment. Positive delta credits the location,
 * negative debits it (and may raise InsufficientStock).
 */
class AdjustStock
{
    public function __construct(private readonly InventoryService $service)
    {
    }

    public function execute(
        Product $product,
        InventoryLocation $location,
        int $delta,
        string $type = InventoryMovement::TYPE_ADJUSTMENT,
        ?User $actor = null,
        ?string $note = null,
    ): InventoryMovement {
        if ($delta === 0) {
            throw new \InvalidArgumentException('Delta must be non-zero.');
        }

        if ($delta > 0) {
            return $this->service->receivePurchase(
                $product, $location, $delta, null, $actor?->id, $note, $type,
            );
        }

        return $this->service->removeStock(
            $product, $location, abs($delta), $type, $actor?->id, null, null, $note,
        );
    }
}
