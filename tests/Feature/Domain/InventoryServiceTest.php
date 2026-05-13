<?php

use App\Domain\Exceptions\InsufficientStock;
use App\Domain\Inventory\Actions\AdjustStock;
use App\Domain\Inventory\Actions\ReceiveStock;
use App\Domain\Inventory\Actions\TransferStock;
use App\Domain\Inventory\InventoryService;
use App\Models\InventoryLocation;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\Property;

beforeEach(function () {
    $this->property = Property::factory()->create();
    $this->product = Product::factory()->create(['property_id' => $this->property->id]);
    $this->storage   = InventoryLocation::factory()->storage()->create(['property_id' => $this->property->id]);
    $this->reception = InventoryLocation::factory()->reception()->create(['property_id' => $this->property->id]);
    $this->service = app(InventoryService::class);
});

it('a product can be created with the required fields', function () {
    $p = Product::factory()->create([
        'property_id' => $this->property->id,
        'name' => 'Coca-Cola 330ml',
        'sku' => 'CC330',
        'sale_price' => 4.00,
    ]);

    expect($p->id)->toBeGreaterThan(0)
        ->and($p->name)->toBe('Coca-Cola 330ml')
        ->and((float) $p->sale_price)->toBe(4.00);
});

it('stock can be received and written to product_stock with a movement', function () {
    $movement = app(ReceiveStock::class)->execute(
        $this->product, $this->storage, 50, 1.5, null, 'initial',
    );

    expect($this->service->stockAt($this->product, $this->storage))->toBe(50)
        ->and($movement->type)->toBe(InventoryMovement::TYPE_PURCHASE)
        ->and($movement->to_location_id)->toBe($this->storage->id)
        ->and($movement->from_location_id)->toBeNull()
        ->and($movement->quantity)->toBe(50);
});

it('stock can be transferred between locations', function () {
    app(ReceiveStock::class)->execute($this->product, $this->storage, 20);

    $movement = app(TransferStock::class)->execute(
        $this->product, $this->storage, $this->reception, 8,
    );

    expect($this->service->stockAt($this->product, $this->storage))->toBe(12)
        ->and($this->service->stockAt($this->product, $this->reception))->toBe(8)
        ->and($movement->type)->toBe(InventoryMovement::TYPE_TRANSFER)
        ->and($movement->from_location_id)->toBe($this->storage->id)
        ->and($movement->to_location_id)->toBe($this->reception->id);
});

it('insufficient stock blocks a sale/transfer/negative adjustment', function () {
    app(ReceiveStock::class)->execute($this->product, $this->storage, 3);

    expect(fn () => app(TransferStock::class)->execute(
        $this->product, $this->storage, $this->reception, 10,
    ))->toThrow(InsufficientStock::class);

    expect(fn () => app(AdjustStock::class)->execute(
        $this->product, $this->storage, -5, InventoryMovement::TYPE_LOSS,
    ))->toThrow(InsufficientStock::class);

    // …and stock is unchanged.
    expect($this->service->stockAt($this->product, $this->storage))->toBe(3);
});

it('movement ledger is appended on every successful change', function () {
    $startCount = InventoryMovement::count();
    app(ReceiveStock::class)->execute($this->product, $this->storage, 10);
    app(TransferStock::class)->execute($this->product, $this->storage, $this->reception, 4);
    app(AdjustStock::class)->execute($this->product, $this->reception, -1, InventoryMovement::TYPE_LOSS);

    expect(InventoryMovement::count() - $startCount)->toBe(3);
});

it('low stock report flags products at or below threshold', function () {
    $p1 = Product::factory()->create(['property_id' => $this->property->id, 'low_stock_threshold' => 5]);
    $p2 = Product::factory()->create(['property_id' => $this->property->id, 'low_stock_threshold' => 5]);

    app(ReceiveStock::class)->execute($p1, $this->storage, 3);  // below threshold
    app(ReceiveStock::class)->execute($p2, $this->storage, 50); // above threshold

    $report = $this->service->lowStockReport($this->property);
    $ids = $report->map(fn ($r) => $r['product']->id)->all();

    expect($ids)->toContain($p1->id)->not->toContain($p2->id);
});
