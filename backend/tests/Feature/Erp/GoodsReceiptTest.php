<?php

declare(strict_types=1);

use App\Modules\Erp\Core\Models\Branch;
use App\Modules\Erp\Inventory\Models\Product;
use App\Modules\Erp\Inventory\Models\ProductVariant;
use App\Modules\Erp\Inventory\Models\SerialItem;
use App\Modules\Erp\Inventory\Models\StockLevel;
use App\Modules\Erp\Inventory\Models\StockMovement;
use App\Modules\Erp\Procurement\Exceptions\GoodsReceiptAlreadyPostedException;
use App\Modules\Erp\Procurement\Models\GoodsReceipt;
use App\Modules\Erp\Procurement\Models\GoodsReceiptLine;
use App\Modules\Erp\Procurement\Models\Supplier;
use App\Modules\Erp\Procurement\Services\GoodsReceiptService;
use App\Support\Ulid;

function makeReceipt(ProductVariant $variant, int $branchId, int $qty, float $unitCost, array $serials = []): GoodsReceipt
{
    $receipt = GoodsReceipt::create([
        'ulid' => Ulid::new(),
        'supplier_id' => Supplier::factory()->create()->id,
        'branch_id' => $branchId,
        'status' => GoodsReceipt::STATUS_DRAFT,
    ]);

    GoodsReceiptLine::create([
        'goods_receipt_id' => $receipt->id,
        'product_variant_id' => $variant->id,
        'qty' => $qty,
        'unit_cost' => $unitCost,
        'serial_nos' => $serials !== [] ? $serials : null,
    ]);

    return $receipt->fresh();
}

it('brings stock on hand and writes a movement when posted', function (): void {
    $branch = Branch::factory()->create();
    $variant = ProductVariant::factory()->create();

    $receipt = makeReceipt($variant, (int) $branch->id, 10, 20.00);
    app(GoodsReceiptService::class)->post($receipt);

    $level = StockLevel::firstOrFail();
    expect($level->qty)->toBe(10)
        ->and($level->branch_id)->toBe($branch->id);

    expect(StockMovement::where('type', 'in')->count())->toBe(1);
});

it('rolls the product weighted-average cost forward across receipts', function (): void {
    $branch = Branch::factory()->create();
    $variant = ProductVariant::factory()->create();
    $productId = $variant->product_id;

    app(GoodsReceiptService::class)->post(makeReceipt($variant, (int) $branch->id, 10, 20.00));
    expect((float) Product::find($productId)->cost)->toBe(20.00);

    // 10 @ 20 already on hand + 10 @ 30 -> 25.00 weighted average
    app(GoodsReceiptService::class)->post(makeReceipt($variant, (int) $branch->id, 10, 30.00));
    expect((float) Product::find($productId)->cost)->toBe(25.00);
});

it('creates serial items for serialized receipts', function (): void {
    $branch = Branch::factory()->create();
    $variant = ProductVariant::factory()->for(Product::factory()->serialized())->create();

    $receipt = makeReceipt($variant, (int) $branch->id, 2, 500.00, ['IMEI-1', 'IMEI-2']);
    app(GoodsReceiptService::class)->post($receipt);

    expect(SerialItem::where('status', SerialItem::STATUS_IN_STOCK)->count())->toBe(2);
});

it('refuses to post the same receipt twice', function (): void {
    $branch = Branch::factory()->create();
    $variant = ProductVariant::factory()->create();
    $receipt = makeReceipt($variant, (int) $branch->id, 5, 10.00);

    $service = app(GoodsReceiptService::class);
    $service->post($receipt);

    expect(fn () => $service->post($receipt->fresh()))
        ->toThrow(GoodsReceiptAlreadyPostedException::class);

    // stock and cost must not double-count
    expect(StockLevel::firstOrFail()->qty)->toBe(5);
});
