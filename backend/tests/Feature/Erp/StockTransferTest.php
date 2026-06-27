<?php

declare(strict_types=1);

use App\Modules\Erp\Core\Models\Branch;
use App\Modules\Erp\Inventory\Exceptions\InsufficientStockException;
use App\Modules\Erp\Inventory\Models\ProductVariant;
use App\Modules\Erp\Inventory\Models\StockLevel;
use App\Modules\Erp\Inventory\Services\StockLedger;
use App\Modules\Erp\Inventory\Services\StockTransferService;

it('moves stock from one branch to another', function (): void {
    $from = Branch::factory()->create();
    $to = Branch::factory()->create();
    $variant = ProductVariant::factory()->create();

    app(StockLedger::class)->receive((int) $variant->id, (int) $from->id, 10, 15.00);

    app(StockTransferService::class)->transfer($variant, (int) $from->id, (int) $to->id, 4);

    $fromQty = StockLevel::where('branch_id', $from->id)->firstOrFail()->qty;
    $toQty = StockLevel::where('branch_id', $to->id)->firstOrFail()->qty;

    expect($fromQty)->toBe(6)->and($toQty)->toBe(4);
});

it('refuses to transfer more than is on hand', function (): void {
    $from = Branch::factory()->create();
    $to = Branch::factory()->create();
    $variant = ProductVariant::factory()->create();

    app(StockLedger::class)->receive((int) $variant->id, (int) $from->id, 3, 15.00);

    expect(fn () => app(StockTransferService::class)->transfer($variant, (int) $from->id, (int) $to->id, 5))
        ->toThrow(InsufficientStockException::class);

    expect(StockLevel::where('branch_id', $from->id)->firstOrFail()->qty)->toBe(3);
});
