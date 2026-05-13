<?php

namespace App\Domain\Pricing\Rules;

use App\Domain\Pricing\PricingContext;

/**
 * Applies if the night's date is in the conditions.dates list (Y-m-d strings).
 */
class HolidayRule extends Rule
{
    public function applies(PricingContext $ctx): bool
    {
        if (! $this->withinValidity($ctx) || ! $this->scopeMatches($ctx)) {
            return false;
        }
        $dates = $this->row->conditions['dates'] ?? [];

        return in_array($ctx->date->toDateString(), $dates, true);
    }
}
