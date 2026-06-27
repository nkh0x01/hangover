<?php

declare(strict_types=1);

namespace App\Modules\Erp\Inventory\Services;

use App\Modules\Erp\Inventory\Exceptions\InsufficientStockException;
use App\Modules\Erp\Inventory\Models\StockLevel;
use App\Modules\Erp\Inventory\Models\StockMovement;
use App\Support\Ulid;
use Illuminate\Database\Eloquent\Model;

/**
 * Single choke point for stock_levels mutations. Every change writes a
 * matching stock_movement so on-hand is always reconstructable from the
 * ledger — no quantity moves without an audit row.
 */
final class StockLedger
{
    public function onHandForVariant(int $variantId): int
    {
        return (int) StockLevel::query()->where('product_variant_id', $variantId)->sum('qty');
    }

    /**
     * @param array<int> $variantIds
     */
    public function onHandForVariants(array $variantIds): int
    {
        if ($variantIds === []) {
            return 0;
        }

        return (int) StockLevel::query()->whereIn('product_variant_id', $variantIds)->sum('qty');
    }

    public function receive(int $variantId, int $branchId, int $qty, float $cost, ?Model $ref = null, ?int $userId = null): void
    {
        $this->adjustLevel($variantId, $branchId, $qty);

        $this->writeMovement(StockMovement::TYPE_IN, $variantId, $qty, $cost, null, $branchId, $ref, $userId);
    }

    public function transfer(int $variantId, int $fromBranchId, int $toBranchId, int $qty, float $cost, ?Model $ref = null, ?int $userId = null): void
    {
        $available = $this->levelQty($variantId, $fromBranchId);

        if ($available < $qty) {
            throw InsufficientStockException::for($variantId, $fromBranchId, $available, $qty);
        }

        $this->adjustLevel($variantId, $fromBranchId, -$qty);
        $this->adjustLevel($variantId, $toBranchId, $qty);

        $this->writeMovement(StockMovement::TYPE_TRANSFER, $variantId, $qty, $cost, $fromBranchId, $toBranchId, $ref, $userId);
    }

    public function issue(int $variantId, int $branchId, int $qty, float $cost, ?Model $ref = null, ?int $userId = null): void
    {
        $available = $this->levelQty($variantId, $branchId);

        if ($available < $qty) {
            throw InsufficientStockException::for($variantId, $branchId, $available, $qty);
        }

        $this->adjustLevel($variantId, $branchId, -$qty);

        $this->writeMovement(StockMovement::TYPE_OUT, $variantId, $qty, $cost, $branchId, null, $ref, $userId);
    }

    private function levelQty(int $variantId, int $branchId): int
    {
        $qty = StockLevel::query()
            ->where('product_variant_id', $variantId)
            ->where('branch_id', $branchId)
            ->value('qty');

        return (int) ($qty ?? 0);
    }

    private function adjustLevel(int $variantId, int $branchId, int $delta): void
    {
        $level = StockLevel::query()->firstOrCreate(
            ['product_variant_id' => $variantId, 'branch_id' => $branchId],
            ['qty' => 0],
        );

        $level->qty += $delta;
        $level->save();
    }

    private function writeMovement(string $type, int $variantId, int $qty, float $cost, ?int $fromBranchId, ?int $toBranchId, ?Model $ref, ?int $userId): void
    {
        StockMovement::create([
            'ulid' => Ulid::new(),
            'type' => $type,
            'product_variant_id' => $variantId,
            'from_branch_id' => $fromBranchId,
            'to_branch_id' => $toBranchId,
            'qty' => $qty,
            'cost' => $cost,
            'ref_type' => $ref !== null ? $ref::class : null,
            'ref_id' => $ref?->getKey(),
            'user_id' => $userId,
        ]);
    }
}
