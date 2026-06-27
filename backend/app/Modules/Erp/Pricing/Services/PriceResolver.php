<?php

declare(strict_types=1);

namespace App\Modules\Erp\Pricing\Services;

use App\Modules\Erp\Pricing\Models\PriceList;
use App\Modules\Erp\Pricing\Models\PriceListItem;

/**
 * Resolves the active selling price for a variant. Specificity wins:
 * a branch+brand list beats a brand-only list beats a global list, all
 * filtered by sale type (retail/wholesale). Returns null when no list
 * prices the variant, so the caller can refuse the sale rather than
 * silently invent a price.
 */
final class PriceResolver
{
    public function resolve(int $variantId, ?int $brandId, ?int $branchId, string $type = PriceList::TYPE_RETAIL): ?PriceListItem
    {
        $candidates = PriceListItem::query()
            ->where('product_variant_id', $variantId)
            ->whereHas('priceList', function ($q) use ($type): void {
                $q->where('is_active', true)->where('type', $type);
            })
            ->with('priceList')
            ->get();

        if ($candidates->isEmpty()) {
            return null;
        }

        return $candidates
            ->sortByDesc(fn (PriceListItem $item): int => $this->specificity($item->priceList, $brandId, $branchId))
            ->first();
    }

    private function specificity(PriceList $list, ?int $brandId, ?int $branchId): int
    {
        $score = 0;

        if ($list->branch_id !== null && $list->branch_id === $branchId) {
            $score += 2;
        }

        if ($list->brand_id !== null && $list->brand_id === $brandId) {
            $score += 1;
        }

        // A list pinned to a different branch/brand than requested is not a
        // match and must never outrank a global list.
        if (($list->branch_id !== null && $list->branch_id !== $branchId)
            || ($list->brand_id !== null && $list->brand_id !== $brandId)) {
            return -1;
        }

        return $score;
    }
}
