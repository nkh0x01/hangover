<?php

namespace App\Domain\Pricing\Rules;

use App\Domain\Pricing\PricingContext;

/**
 * Applies when arrival is within N days from "now".
 * Conditions: max_days_to_arrival (int).
 */
class LastMinuteRule extends Rule
{
    public function applies(PricingContext $ctx): bool
    {
        if (! $this->withinValidity($ctx) || ! $this->scopeMatches($ctx)) {
            return false;
        }
        $max = (int) ($this->row->conditions['max_days_to_arrival'] ?? 0);

        return $ctx->daysToArrival >= 0 && $ctx->daysToArrival <= $max;
    }
}
