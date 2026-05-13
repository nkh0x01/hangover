<?php

namespace App\Domain\Inventory\Actions;

use App\Domain\Inventory\InventoryService;
use App\Domain\Reservations\Support\ReservationTotals;
use App\Models\InventoryLocation;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\Reservation;
use App\Models\ReservationCharge;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Sell a product to an in-house guest. Decrements stock at the chosen
 * location (typically the room's minibar or reception) and adds a
 * 'product' charge to the reservation folio.
 */
class SellToReservation
{
    public function __construct(
        private readonly InventoryService $service,
        private readonly ReservationTotals $totals,
    ) {
    }

    public function execute(
        Reservation $reservation,
        Product $product,
        InventoryLocation $location,
        int $quantity,
        ?User $actor = null,
        ?string $note = null,
    ): ReservationCharge {
        if ($quantity <= 0) {
            throw new \InvalidArgumentException('Quantity must be positive.');
        }
        if ($product->property_id !== $reservation->property_id) {
            throw new \InvalidArgumentException('Product is from another property.');
        }

        return DB::transaction(function () use ($reservation, $product, $location, $quantity, $actor, $note) {
            $unit = (float) $product->sale_price;
            $total = round($unit * $quantity, 2);

            $charge = ReservationCharge::create([
                'reservation_id' => $reservation->id,
                'type' => ReservationCharge::TYPE_PRODUCT,
                'description' => $product->name,
                'quantity' => $quantity,
                'unit_price' => $unit,
                'total' => $total,
                'taxable' => true,
                'tax_rate' => (float) $product->tax_rate,
                'added_by' => $actor?->id,
                'added_at' => now(),
            ]);

            $movement = null;
            if ($product->track_stock) {
                $movement = $this->service->removeStock(
                    $product, $location, $quantity,
                    InventoryMovement::TYPE_SALE,
                    $actor?->id,
                    $reservation->id,
                    null,
                    $note,
                );
            }

            $this->totals->recompute($reservation);

            // For traceability, store the movement id in the note if we wrote one
            if ($movement) {
                $charge->update(['added_at' => $movement->occurred_at]);
            }

            return $charge->fresh();
        });
    }
}
