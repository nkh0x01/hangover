<?php

namespace App\Domain\Exceptions;

use App\Models\InventoryLocation;
use App\Models\Product;

class InsufficientStock extends DomainException
{
    public static function for(Product $product, InventoryLocation $location, int $requested, int $available): self
    {
        return new self(sprintf(
            'Insufficient stock for "%s" at %s: requested %d, available %d.',
            $product->name,
            $location->name,
            $requested,
            $available,
        ));
    }
}
