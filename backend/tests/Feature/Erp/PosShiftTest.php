<?php

declare(strict_types=1);

use App\Modules\Erp\Core\Models\Branch;
use App\Modules\Erp\Inventory\Models\Product;
use App\Modules\Erp\Inventory\Models\ProductVariant;
use App\Modules\Erp\Inventory\Services\StockLedger;
use App\Modules\Erp\Pos\Models\CashMovement;
use App\Modules\Erp\Pos\Models\PosShift;
use App\Modules\Erp\Pos\Services\PosSaleService;
use App\Modules\Erp\Pos\Services\PosShiftService;
use App\Modules\Identity\Models\User;

function freshShift(float $openingCash = 100.00): array
{
    $branch = Branch::factory()->create();
    $user = User::factory()->create(['branch_id' => $branch->id]);
    $product = Product::factory()->create(['cost' => 30.00]);
    $variant = ProductVariant::factory()->for($product)->create();
    app(StockLedger::class)->receive((int) $variant->id, (int) $branch->id, 10, 30.00);

    $shift = app(PosShiftService::class)->open((int) $branch->id, (int) $user->id, $openingCash);

    return [$shift, $variant];
}

it('builds an X report with sales and expected cash without closing the shift', function (): void {
    [$shift, $variant] = freshShift(openingCash: 100.00);

    app(PosSaleService::class)->register(
        $shift,
        [['variant_id' => (int) $variant->id, 'qty' => 1, 'unit_price' => 50.00]],
        [['method' => 'cash', 'amount' => 50.00]],
    );

    $report = app(PosShiftService::class)->xReport($shift);

    expect($report['sales_count'])->toBe(1)
        ->and($report['gross_total'])->toBe(50.00)
        ->and($report['expected_cash'])->toBe(150.00) // 100 opening + 50 cash sale
        ->and($shift->fresh()->isOpen())->toBeTrue();
});

it('closes the shift with a Z report and computes cash variance', function (): void {
    [$shift, $variant] = freshShift(openingCash: 100.00);

    app(PosSaleService::class)->register(
        $shift,
        [['variant_id' => (int) $variant->id, 'qty' => 1, 'unit_price' => 50.00]],
        [['method' => 'cash', 'amount' => 50.00]],
    );

    // counted 148 vs expected 150 -> -2.00 short
    $closed = app(PosShiftService::class)->close($shift, 148.00);

    // z_report values round-trip through JSON, so compare numerically.
    expect($closed->status)->toBe(PosShift::STATUS_CLOSED)
        ->and($closed->z_report['expected_cash'])->toEqual(150.00)
        ->and($closed->z_report['counted_cash'])->toEqual(148.00)
        ->and($closed->z_report['cash_variance'])->toEqual(-2.00)
        ->and($closed->closed_at)->not->toBeNull();
});

it('reflects paid-out cash movements in expected cash', function (): void {
    [$shift, $variant] = freshShift(openingCash: 100.00);

    CashMovement::create([
        'shift_id' => $shift->id,
        'branch_id' => $shift->branch_id,
        'type' => CashMovement::TYPE_OUT,
        'amount' => 20.00,
        'reason' => 'supplier cash',
    ]);

    $report = app(PosShiftService::class)->xReport($shift);

    expect($report['expected_cash'])->toBe(80.00); // 100 opening - 20 out
});
