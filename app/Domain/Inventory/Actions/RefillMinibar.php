<?php

namespace App\Domain\Inventory\Actions;

use App\Domain\Inventory\InventoryService;
use App\Models\InventoryLocation;
use App\Models\InventoryMovement;
use App\Models\Room;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Compares each room_minibar_items.par_level vs the current stock at the
 * room's minibar location, then transfers the diff from `storage` to the
 * minibar. Returns the list of created transfer movements (one per product).
 *
 * If storage doesn't have enough stock to satisfy a product, that product
 * is skipped and reported back to the caller — we never partially fulfil.
 */
class RefillMinibar
{
    public function __construct(private readonly InventoryService $service)
    {
    }

    /**
     * @return array{
     *   refilled: list<InventoryMovement>,
     *   skipped:  list<array{product_id: int, requested: int, available: int}>,
     * }
     */
    public function execute(Room $room, ?User $actor = null): array
    {
        return DB::transaction(function () use ($room, $actor) {
            $room->load('minibarItems.product', 'minibarLocation');

            $minibar = $room->minibarLocation;
            if (! $minibar) {
                throw new \RuntimeException("Room {$room->number} has no minibar location.");
            }

            $storage = InventoryLocation::query()
                ->where('property_id', $room->property_id)
                ->where('type', InventoryLocation::TYPE_STORAGE)
                ->first();
            if (! $storage) {
                throw new \RuntimeException('No storage location configured for this property.');
            }

            $refilled = [];
            $skipped = [];

            foreach ($room->minibarItems as $item) {
                $product = $item->product;
                if (! $product || ! $product->active) {
                    continue;
                }

                $current = $this->service->stockAt($product, $minibar);
                $needed = max(0, $item->par_level - $current);
                if ($needed === 0) {
                    continue;
                }

                $available = $this->service->stockAt($product, $storage);
                if ($product->track_stock && $available < $needed) {
                    $skipped[] = [
                        'product_id' => $product->id,
                        'requested' => $needed,
                        'available' => $available,
                    ];
                    continue;
                }

                $refilled[] = $this->service->transfer(
                    $product, $storage, $minibar, $needed,
                    $actor?->id,
                    "Minibar refill for room {$room->number}",
                    InventoryMovement::TYPE_REFILL,
                );
            }

            return ['refilled' => $refilled, 'skipped' => $skipped];
        });
    }
}
