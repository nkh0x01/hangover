<?php

declare(strict_types=1);

namespace App\Modules\Erp\Inventory\Services;

/**
 * Weighted-average cost (COGS source). Pure arithmetic so it is testable in
 * isolation and reused wherever cost must be rolled forward on receipt.
 */
final class WeightedAverageCost
{
    /**
     * New average = (onHand * oldCost + receivedQty * receivedCost) / total.
     * Falls back to the received cost when there is nothing on hand.
     */
    public static function next(int $onHandQty, float $oldCost, int $receivedQty, float $receivedCost): float
    {
        $total = $onHandQty + $receivedQty;

        if ($total <= 0) {
            return round($receivedCost, 2);
        }

        return round((($onHandQty * $oldCost) + ($receivedQty * $receivedCost)) / $total, 2);
    }
}
