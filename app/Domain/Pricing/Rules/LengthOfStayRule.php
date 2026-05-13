<?php

namespace App\Domain\Pricing\Rules;

use App\Domain\Pricing\PricingContext;

/**
 * Applies when the stay length is at least N nights.
 * Conditions: min_nights (int).
 */
class LengthOfStayRule extends Rule
{
    public function applies(PricingContext $ctx): bool
    {
        if (! $this->withinValidity($ctx) || ! $this->scopeMatches($ctx)) {
            return false;
        }
        $min = (int) ($this->row->conditions['min_nights'] ?? 1);

        return $ctx->stayLength >= $min;
    }
}
