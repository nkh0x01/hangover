<?php

use App\Domain\Inventory\Actions\ReceiveStock;
use App\Domain\Inventory\Actions\RefillMinibar;
use App\Domain\Inventory\InventoryService;
use App\Models\InventoryLocation;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\RoomMinibarItem;
use Tests\Support\PmsTestFactory;

beforeEach(function () {
    $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    $this->p = new PmsTestFactory();
    $this->room = $this->p->room(0);
    $this->product = Product::factory()->create([
        'property_id' => $this->p->property->id,
        'name' => 'Coca-Cola 330ml',
        'sale_price' => 4.00,
    ]);

    $this->storage = InventoryLocation::factory()->storage()->create([
        'property_id' => $this->p->property->id,
    ]);
    $this->minibar = InventoryLocation::create([
        'property_id' => $this->p->property->id,
        'type' => InventoryLocation::TYPE_ROOM_MINIBAR,
        'room_id' => $this->room->id,
        'name' => "Minibar — {$this->room->number}",
        'active' => true,
    ]);

    RoomMinibarItem::create([
        'room_id' => $this->room->id,
        'product_id' => $this->product->id,
        'par_level' => 3,
    ]);

    app(ReceiveStock::class)->execute($this->product, $this->storage, 10);
});

it('minibar refill creates a transfer movement of the missing quantity', function () {
    $service = app(InventoryService::class);
    // Minibar starts empty; par is 3 → expect transfer of 3.
    expect($service->stockAt($this->product, $this->minibar))->toBe(0);

    $result = app(RefillMinibar::class)->execute($this->room);

    expect($result['refilled'])->toHaveCount(1)
        ->and($result['skipped'])->toBeEmpty()
        ->and($service->stockAt($this->product, $this->minibar))->toBe(3)
        ->and($service->stockAt($this->product, $this->storage))->toBe(7);

    $movement = $result['refilled'][0];
    expect($movement->type)->toBe(InventoryMovement::TYPE_REFILL)
        ->and($movement->quantity)->toBe(3)
        ->and($movement->from_location_id)->toBe($this->storage->id)
        ->and($movement->to_location_id)->toBe($this->minibar->id);
});

it('refill skips items when storage cannot satisfy the par level', function () {
    // Wipe storage so 3 are needed but only 0 available.
    \App\Models\ProductStock::query()
        ->where('product_id', $this->product->id)
        ->where('inventory_location_id', $this->storage->id)
        ->update(['quantity' => 0]);

    $result = app(RefillMinibar::class)->execute($this->room);

    expect($result['refilled'])->toBeEmpty()
        ->and($result['skipped'])->toHaveCount(1)
        ->and($result['skipped'][0]['product_id'])->toBe($this->product->id)
        ->and($result['skipped'][0]['requested'])->toBe(3)
        ->and($result['skipped'][0]['available'])->toBe(0);
});

it('refill is a no-op when minibar already at par', function () {
    // Pre-fill minibar to par.
    app(InventoryService::class)->transfer($this->product, $this->storage, $this->minibar, 3);

    $result = app(RefillMinibar::class)->execute($this->room);

    expect($result['refilled'])->toBeEmpty()
        ->and($result['skipped'])->toBeEmpty();
});
