<?php

declare(strict_types=1);

use App\Modules\Erp\Core\Models\Branch;
use App\Modules\Erp\Inventory\Models\Product;
use App\Modules\Erp\Inventory\Models\ProductVariant;
use App\Modules\Erp\Inventory\Models\StockLevel;
use App\Modules\Erp\Inventory\Models\StockMovement;
use App\Modules\Erp\Inventory\Services\StockLedger;
use App\Modules\Erp\Pos\Exceptions\PaymentMismatchException;
use App\Modules\Erp\Pos\Exceptions\ShiftNotOpenException;
use App\Modules\Erp\Pos\Models\PosSale;
use App\Modules\Erp\Pos\Models\PosSaleItem;
use App\Modules\Erp\Pos\Models\PosShift;
use App\Modules\Erp\Pos\Services\PosSaleService;
use App\Modules\Identity\Models\User;

function openShiftWithStock(float $cost = 40.00, int $qty = 5, bool $vat = true): array
{
    $branch = Branch::factory()->create();
    $user = User::factory()->create(['branch_id' => $branch->id]);
    $product = Product::factory()->create(['vat_applicable' => $vat, 'cost' => $cost]);
    $variant = ProductVariant::factory()->for($product)->create();

    app(StockLedger::class)->receive((int) $variant->id, (int) $branch->id, $qty, $cost);

    $shift = PosShift::create([
        'branch_id' => $branch->id,
        'user_id' => $user->id,
        'status' => PosShift::STATUS_OPEN,
        'opening_cash' => 100.00,
        'opened_at' => now(),
    ]);

    return [$shift, $variant];
}

it('rings a sale, snapshots cost, extracts VAT and decrements stock', function (): void {
    [$shift, $variant] = openShiftWithStock(cost: 40.00, qty: 5);

    $sale = app(PosSaleService::class)->register(
        $shift,
        [['variant_id' => (int) $variant->id, 'qty' => 2, 'unit_price' => 118.00]],
        [['method' => 'cash', 'amount' => 236.00]],
    );

    $item = PosSaleItem::firstOrFail();
    expect((float) $sale->total)->toBe(236.00)
        ->and((float) $sale->vat)->toBe(36.00)         // 2 * (118 * 18/118)
        ->and((float) $item->cost)->toBe(40.00)        // COGS snapshot
        ->and((float) $sale->subtotal)->toBe(236.00);

    // stock dropped from 5 to 3 and an OUT movement exists
    expect(StockLevel::firstOrFail()->qty)->toBe(3)
        ->and(StockMovement::where('type', 'out')->count())->toBe(1);
});

it('keeps the cost snapshot even after the product cost later changes', function (): void {
    [$shift, $variant] = openShiftWithStock(cost: 40.00, qty: 5);

    app(PosSaleService::class)->register(
        $shift,
        [['variant_id' => (int) $variant->id, 'qty' => 1, 'unit_price' => 118.00]],
        [['method' => 'cash', 'amount' => 118.00]],
    );

    Product::query()->where('id', $variant->product_id)->update(['cost' => 99.00]);

    expect((float) PosSaleItem::firstOrFail()->cost)->toBe(40.00);
});

it('refuses a sale when payments do not match the total', function (): void {
    [$shift, $variant] = openShiftWithStock();

    expect(fn () => app(PosSaleService::class)->register(
        $shift,
        [['variant_id' => (int) $variant->id, 'qty' => 1, 'unit_price' => 100.00]],
        [['method' => 'cash', 'amount' => 90.00]],
    ))->toThrow(PaymentMismatchException::class);

    expect(PosSale::count())->toBe(0);
});

it('refuses a sale against a closed shift', function (): void {
    [$shift, $variant] = openShiftWithStock();
    $shift->update(['status' => PosShift::STATUS_CLOSED]);

    expect(fn () => app(PosSaleService::class)->register(
        $shift->fresh(),
        [['variant_id' => (int) $variant->id, 'qty' => 1, 'unit_price' => 100.00]],
        [['method' => 'cash', 'amount' => 100.00]],
    ))->toThrow(ShiftNotOpenException::class);
});

it('is idempotent on sale_uuid so an offline retry never double-rings', function (): void {
    [$shift, $variant] = openShiftWithStock(qty: 5);
    $uuid = '11111111-1111-1111-1111-111111111111';

    $first = app(PosSaleService::class)->register(
        $shift,
        [['variant_id' => (int) $variant->id, 'qty' => 1, 'unit_price' => 100.00]],
        [['method' => 'cash', 'amount' => 100.00]],
        ['sale_uuid' => $uuid],
    );

    $second = app(PosSaleService::class)->register(
        $shift,
        [['variant_id' => (int) $variant->id, 'qty' => 1, 'unit_price' => 100.00]],
        [['method' => 'cash', 'amount' => 100.00]],
        ['sale_uuid' => $uuid],
    );

    expect($second->id)->toBe($first->id)
        ->and(PosSale::count())->toBe(1)
        ->and(StockLevel::firstOrFail()->qty)->toBe(4); // decremented once, not twice
});
