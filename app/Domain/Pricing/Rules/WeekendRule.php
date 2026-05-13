<?php

namespace App\Domain\Pricing\Rules;

use App\Domain\Pricing\PricingContext;

/**
 * Applies on the days specified in conditions.days (ISO 1..7, Mon..Sun).
 * Default is Friday + Saturday.
 */
class WeekendRule extends Rule
{
    public function applies(PricingContext $ctx): bool
    {
        if (! $this->withinValidity($ctx) || ! $this->scopeMatches($ctx)) {
            return false;
        }
        $days = $this->row->conditions['days'] ?? [5, 6];

        return in_array($ctx->date->dayOfWeekIso, array_map('intval', $days), true);
    }
}
