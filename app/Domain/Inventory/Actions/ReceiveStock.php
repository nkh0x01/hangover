<?php

namespace App\Domain\Inventory\Actions;

use App\Domain\Inventory\InventoryService;
use App\Models\InventoryLocation;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\User;

class ReceiveStock
{
    public function __construct(private readonly InventoryService $service)
    {
    }

    public function execute(
        Product $product,
        InventoryLocation $location,
        int $quantity,
        ?float $unitCost = null,
        ?User $actor = null,
        ?string $note = null,
    ): InventoryMovement {
        return $this->service->receivePurchase(
            $product, $location, $quantity, $unitCost, $actor?->id, $note,
        );
    }
}
