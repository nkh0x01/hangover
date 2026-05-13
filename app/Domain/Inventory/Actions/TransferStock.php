<?php

namespace App\Domain\Inventory\Actions;

use App\Domain\Inventory\InventoryService;
use App\Models\InventoryLocation;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\User;

class TransferStock
{
    public function __construct(private readonly InventoryService $service)
    {
    }

    public function execute(
        Product $product,
        InventoryLocation $from,
        InventoryLocation $to,
        int $quantity,
        ?User $actor = null,
        ?string $note = null,
        string $type = InventoryMovement::TYPE_TRANSFER,
    ): InventoryMovement {
        return $this->service->transfer(
            $product, $from, $to, $quantity, $actor?->id, $note, $type,
        );
    }
}
