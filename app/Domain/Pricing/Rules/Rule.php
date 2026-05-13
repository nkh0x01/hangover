<?php

namespace App\Domain\Pricing\Rules;

use App\Domain\Pricing\PricingContext;
use App\Models\PricingRule;

/**
 * Pricing-rule contract. Implementations decide whether the rule applies
 * to a given (date, room_type, stay) context and how to adjust the
 * running price. Pure — no DB access during the engine pass.
 */
abstract class Rule
{
    public function __construct(public readonly PricingRule $row)
    {
    }

    abstract public function applies(PricingContext $ctx): bool;

    /**
     * Adjust the running price. Default implementation handles the three
     * action shapes:
     *   {type: 'percent',  value: 15}   -> multiply by 1.15
     *   {type: 'absolute', value: 50}   -> add 50
     *   {type: 'set',      value: 200}  -> replace with 200
     */
    public function apply(float $price, PricingContext $ctx): float
    {
        $action = $this->row->action ?? [];
        $type = $action['type'] ?? 'percent';
        $value = (float) ($action['value'] ?? 0);

        return match ($type) {
            'percent'  => round($price * (1 + $value / 100), 2),
            'absolute' => round($price + $value, 2),
            'set'      => round($value, 2),
            default    => $price,
        };
    }

    /**
     * Common check: is the row active and is the night's date within
     * valid_from..valid_to (inclusive)? Rule-specific applies() should
     * compose this in.
     */
    protected function withinValidity(PricingContext $ctx): bool
    {
        if (! $this->row->active) {
            return false;
        }
        if ($this->row->valid_from && $ctx->date->lt($this->row->valid_from)) {
            return false;
        }
        if ($this->row->valid_to && $ctx->date->gt($this->row->valid_to)) {
            return false;
        }
        return true;
    }

    /**
     * Common check: does scope match the room type of the night?
     */
    protected function scopeMatches(PricingContext $ctx): bool
    {
        return match ($this->row->scope) {
            PricingRule::SCOPE_PROPERTY  => true,
            PricingRule::SCOPE_ROOM_TYPE => $this->row->room_type_id === $ctx->roomType->id,
            PricingRule::SCOPE_ROOM      => true, // per-room scoping applied at quote-stage
            default                       => true,
        };
    }
}
