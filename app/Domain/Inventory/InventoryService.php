<?php

namespace App\Domain\Inventory;

use App\Domain\Exceptions\InsufficientStock;
use App\Models\InventoryLocation;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\Property;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Single gate for every stock change. Other code MUST NOT touch
 * product_stock directly — call applyDelta() / receivePurchase() / etc.
 * Every mutation is paired with an inventory_movements row in the same
 * transaction, and stock rows are pessimistically locked.
 */
class InventoryService
{
    /**
     * @internal Apply a signed delta to a location's stock, creating the row
     *           if it doesn't exist. Returns the post-delta quantity.
     *           Caller must already hold a transaction.
     */
    public function applyDelta(Product $product, InventoryLocation $location, int $delta): int
    {
        $stock = ProductStock::query()
            ->where('product_id', $product->id)
            ->where('inventory_location_id', $location->id)
            ->lockForUpdate()
            ->first();

        if (! $stock) {
            $stock = new ProductStock([
                'product_id' => $product->id,
                'inventory_location_id' => $location->id,
                'quantity' => 0,
            ]);
            // Persist with zero so the row exists and is lockable on future runs.
            $stock->save();
            $stock = ProductStock::query()
                ->where('id', $stock->id)
                ->lockForUpdate()
                ->first();
        }

        $next = $stock->quantity + $delta;

        if ($product->track_stock && $next < 0) {
            throw InsufficientStock::for($product, $location, abs($delta), $stock->quantity);
        }

        $stock->fill(['quantity' => $next])->save();

        return $next;
    }

    /**
     * Receive new stock (purchase from supplier, return, etc.).
     */
    public function receivePurchase(
        Product $product,
        InventoryLocation $to,
        int $quantity,
        ?float $unitCost = null,
        ?int $userId = null,
        ?string $note = null,
        string $type = InventoryMovement::TYPE_PURCHASE,
    ): InventoryMovement {
        $this->guardPositive($quantity);
        $this->guardSameProperty($product, $to);

        return DB::transaction(function () use ($product, $to, $quantity, $unitCost, $userId, $note, $type) {
            $this->applyDelta($product, $to, +$quantity);

            return InventoryMovement::create([
                'property_id' => $product->property_id,
                'product_id' => $product->id,
                'from_location_id' => null,
                'to_location_id' => $to->id,
                'type' => $type,
                'quantity' => $quantity,
                'unit_cost' => $unitCost,
                'user_id' => $userId,
                'note' => $note,
                'occurred_at' => now(),
            ]);
        });
    }

    /**
     * Move stock between two locations within the property.
     */
    public function transfer(
        Product $product,
        InventoryLocation $from,
        InventoryLocation $to,
        int $quantity,
        ?int $userId = null,
        ?string $note = null,
        string $type = InventoryMovement::TYPE_TRANSFER,
    ): InventoryMovement {
        $this->guardPositive($quantity);
        $this->guardSameProperty($product, $from, $to);

        if ($from->id === $to->id) {
            throw new \InvalidArgumentException('Source and destination locations are the same.');
        }

        return DB::transaction(function () use ($product, $from, $to, $quantity, $userId, $note, $type) {
            // Always lock the lower-id row first to avoid deadlocks under contention.
            $first  = min($from->id, $to->id);
            $second = max($from->id, $to->id);
            ProductStock::query()
                ->where('product_id', $product->id)
                ->whereIn('inventory_location_id', [$first, $second])
                ->orderBy('inventory_location_id')
                ->lockForUpdate()
                ->get();

            $this->applyDelta($product, $from, -$quantity);
            $this->applyDelta($product, $to, +$quantity);

            return InventoryMovement::create([
                'property_id' => $product->property_id,
                'product_id' => $product->id,
                'from_location_id' => $from->id,
                'to_location_id' => $to->id,
                'type' => $type,
                'quantity' => $quantity,
                'user_id' => $userId,
                'note' => $note,
                'occurred_at' => now(),
            ]);
        });
    }

    /**
     * Take stock out of the property (sale, loss, damage, negative adjustment).
     * Pass payment_id when this corresponds to a recorded payment.
     */
    public function removeStock(
        Product $product,
        InventoryLocation $from,
        int $quantity,
        string $type,
        ?int $userId = null,
        ?int $reservationId = null,
        ?int $paymentId = null,
        ?string $note = null,
    ): InventoryMovement {
        $this->guardPositive($quantity);
        $this->guardSameProperty($product, $from);

        return DB::transaction(function () use ($product, $from, $quantity, $type, $userId, $reservationId, $paymentId, $note) {
            $this->applyDelta($product, $from, -$quantity);

            return InventoryMovement::create([
                'property_id' => $product->property_id,
                'product_id' => $product->id,
                'from_location_id' => $from->id,
                'to_location_id' => null,
                'type' => $type,
                'quantity' => $quantity,
                'reservation_id' => $reservationId,
                'payment_id' => $paymentId,
                'user_id' => $userId,
                'note' => $note,
                'occurred_at' => now(),
            ]);
        });
    }

    public function stockAt(Product $product, InventoryLocation $location): int
    {
        return (int) (ProductStock::query()
            ->where('product_id', $product->id)
            ->where('inventory_location_id', $location->id)
            ->value('quantity') ?? 0);
    }

    /**
     * Products at or below their low_stock_threshold (computed from total
     * stock across all locations).
     *
     * @return Collection<int, array{product: Product, total: int}>
     */
    public function lowStockReport(Property $property): Collection
    {
        return Product::query()
            ->where('property_id', $property->id)
            ->where('active', true)
            ->where('track_stock', true)
            ->withSum('stocks as total_stock', 'quantity')
            ->get()
            ->filter(fn (Product $p) => (int) ($p->total_stock ?? 0) <= $p->low_stock_threshold)
            ->map(fn (Product $p) => ['product' => $p, 'total' => (int) ($p->total_stock ?? 0)])
            ->values();
    }

    private function guardPositive(int $quantity): void
    {
        if ($quantity <= 0) {
            throw new \InvalidArgumentException("Quantity must be positive, got {$quantity}.");
        }
    }

    private function guardSameProperty(Product $product, InventoryLocation ...$locations): void
    {
        foreach ($locations as $loc) {
            if ($loc->property_id !== $product->property_id) {
                throw new \InvalidArgumentException(
                    'Product and location belong to different properties.',
                );
            }
        }
    }
}
