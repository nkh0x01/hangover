<?php

namespace App\Domain\Pricing\Rules;

use App\Domain\Pricing\PricingContext;

/**
 * Applies for every date inside valid_from..valid_to (no further conditions).
 */
class SeasonalRule extends Rule
{
    public function applies(PricingContext $ctx): bool
    {
        return $this->withinValidity($ctx) && $this->scopeMatches($ctx);
    }
}
