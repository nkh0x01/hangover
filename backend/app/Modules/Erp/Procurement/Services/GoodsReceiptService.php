<?php

declare(strict_types=1);

namespace App\Modules\Erp\Procurement\Services;

use App\Modules\Erp\Inventory\Models\Product;
use App\Modules\Erp\Inventory\Models\ProductVariant;
use App\Modules\Erp\Inventory\Models\SerialItem;
use App\Modules\Erp\Inventory\Services\StockLedger;
use App\Modules\Erp\Inventory\Services\WeightedAverageCost;
use App\Modules\Erp\Procurement\Exceptions\GoodsReceiptAlreadyPostedException;
use App\Modules\Erp\Procurement\Models\GoodsReceipt;
use App\Modules\Erp\Procurement\Models\GoodsReceiptLine;
use App\Modules\Erp\Procurement\Models\PurchaseOrder;
use App\Modules\Erp\Procurement\Models\PurchaseOrderLine;
use Illuminate\Support\Facades\DB;

/**
 * Posts a goods receipt: brings stock on hand, rolls the product
 * weighted-average cost forward (COGS source, S1), records serial items and
 * advances the linked purchase order. Posting is idempotent-guarded so a
 * receipt can never be applied twice.
 */
final class GoodsReceiptService
{
    public function __construct(
        private readonly StockLedger $ledger,
    ) {}

    public function post(GoodsReceipt $receipt): GoodsReceipt
    {
        if ($receipt->status === GoodsReceipt::STATUS_POSTED) {
            throw GoodsReceiptAlreadyPostedException::for((int) $receipt->id);
        }

        return DB::transaction(function () use ($receipt): GoodsReceipt {
            $receipt->loadMissing('lines');

            foreach ($receipt->lines as $line) {
                $this->applyLine($receipt, $line);
            }

            $receipt->status = GoodsReceipt::STATUS_POSTED;
            $receipt->received_at ??= now();
            $receipt->save();

            if ($receipt->purchase_order_id !== null) {
                $this->advancePurchaseOrder($receipt->purchaseOrder()->first());
            }

            return $receipt;
        });
    }

    private function applyLine(GoodsReceipt $receipt, GoodsReceiptLine $line): void
    {
        $variant = ProductVariant::query()->findOrFail($line->product_variant_id);
        $product = Product::query()->findOrFail($variant->product_id);

        // Roll the weighted-average cost forward using on-hand BEFORE this
        // receipt lands, across all variants of the product (cost is
        // product-level). Lines are applied sequentially so multiple lines
        // for the same product accumulate correctly.
        $variantIds = $product->variants()->pluck('id')->map(fn ($id): int => (int) $id)->all();
        $onHand = $this->ledger->onHandForVariants($variantIds);

        $product->cost = WeightedAverageCost::next(
            $onHand,
            (float) $product->cost,
            (int) $line->qty,
            (float) $line->unit_cost,
        );
        $product->save();

        $this->ledger->receive(
            (int) $line->product_variant_id,
            (int) $receipt->branch_id,
            (int) $line->qty,
            (float) $line->unit_cost,
            $receipt,
            $receipt->received_by !== null ? (int) $receipt->received_by : null,
        );

        $this->createSerials($receipt, $line);

        if ($receipt->purchase_order_id !== null) {
            $this->advanceOrderLine($receipt, $line);
        }
    }

    private function createSerials(GoodsReceipt $receipt, GoodsReceiptLine $line): void
    {
        foreach ($line->serial_nos ?? [] as $serial) {
            SerialItem::create([
                'product_variant_id' => $line->product_variant_id,
                'branch_id' => $receipt->branch_id,
                'serial_no' => $serial,
                'status' => SerialItem::STATUS_IN_STOCK,
            ]);
        }
    }

    private function advanceOrderLine(GoodsReceipt $receipt, GoodsReceiptLine $line): void
    {
        PurchaseOrderLine::query()
            ->where('purchase_order_id', $receipt->purchase_order_id)
            ->where('product_variant_id', $line->product_variant_id)
            ->each(function (PurchaseOrderLine $poLine) use ($line): void {
                $poLine->qty_received += (int) $line->qty;
                $poLine->save();
            });
    }

    private function advancePurchaseOrder(?PurchaseOrder $order): void
    {
        if ($order === null) {
            return;
        }

        $order->loadMissing('lines');
        $fullyReceived = $order->lines->every(fn (PurchaseOrderLine $l): bool => $l->qty_received >= $l->qty_ordered);

        $order->status = $fullyReceived ? PurchaseOrder::STATUS_RECEIVED : PurchaseOrder::STATUS_PARTIAL;
        $order->save();
    }
}
