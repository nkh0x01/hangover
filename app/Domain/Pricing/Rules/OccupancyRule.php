<?php

namespace App\Domain\Pricing\Rules;

use App\Domain\Pricing\PricingContext;

/**
 * Applies when the property's occupancy on that date is in [min, max].
 * Conditions: min_occ (0..1), max_occ (0..1; default 1.0).
 */
class OccupancyRule extends Rule
{
    public function applies(PricingContext $ctx): bool
    {
        if (! $this->withinValidity($ctx) || ! $this->scopeMatches($ctx)) {
            return false;
        }
        $min = (float) ($this->row->conditions['min_occ'] ?? 0.0);
        $max = (float) ($this->row->conditions['max_occ'] ?? 1.0);

        return $ctx->occupancyPercent >= $min && $ctx->occupancyPercent <= $max;
    }
}
