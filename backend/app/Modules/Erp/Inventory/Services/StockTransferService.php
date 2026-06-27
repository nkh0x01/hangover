<?php

declare(strict_types=1);

namespace App\Modules\Erp\Inventory\Services;

use App\Modules\Erp\Inventory\Models\Product;
use App\Modules\Erp\Inventory\Models\ProductVariant;
use App\Modules\Erp\Inventory\Models\StockMovement;
use Illuminate\Support\Facades\DB;

/**
 * Moves stock between branches. S1 implements the movement mechanics only;
 * the RS.ge transfer waybill gate (open + verify ACTIVE before the goods
 * move, then stamp waybill_id) layers on in S4 — a movement must not be
 * trusted as legal until that verification exists.
 */
final class StockTransferService
{
    public function __construct(
        private readonly StockLedger $ledger,
    ) {}

    public function transfer(ProductVariant $variant, int $fromBranchId, int $toBranchId, int $qty, ?int $userId = null): StockMovement
    {
        return DB::transaction(function () use ($variant, $fromBranchId, $toBranchId, $qty, $userId): StockMovement {
            /** @var Product $product */
            $product = $variant->product()->firstOrFail();

            $this->ledger->transfer(
                (int) $variant->id,
                $fromBranchId,
                $toBranchId,
                $qty,
                (float) $product->cost,
                null,
                $userId,
            );

            return StockMovement::query()
                ->where('product_variant_id', $variant->id)
                ->where('type', StockMovement::TYPE_TRANSFER)
                ->latest('id')
                ->firstOrFail();
        });
    }
}
