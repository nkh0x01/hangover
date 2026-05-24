<?php

declare(strict_types=1);

namespace App\Modules\Financing\Services\Rules;

use App\Modules\Financing\Dto\BusinessProfileDto;
use App\Modules\Financing\Dto\RuleEvaluationResult;
use App\Modules\Financing\Models\FundingProgramRule;

/**
 * Generic numeric range rule. The concrete value extracted from the profile
 * depends on the rule_type. The criteria can have {min, max} (either optional).
 */
final class RangeRule implements RuleStrategy
{
    public function __construct(
        private readonly string $field,
        private readonly string $matchedLabelKa,
        private readonly string $missingLabelKa,
    ) {}

    public function evaluate(BusinessProfileDto $profile, FundingProgramRule $rule): RuleEvaluationResult
    {
        $value = $this->resolveValue($profile);
        $min = isset($rule->criteria['min']) ? (float) $rule->criteria['min'] : null;
        $max = isset($rule->criteria['max']) ? (float) $rule->criteria['max'] : null;

        $passed = true;
        if ($value === null) {
            $passed = ! $rule->is_required;
        } else {
            if ($min !== null && $value < $min) {
                $passed = false;
            }
            if ($max !== null && $value > $max) {
                $passed = false;
            }
        }

        return new RuleEvaluationResult(
            rule_type: $rule->rule_type,
            passed: $passed,
            is_required: $rule->is_required,
            weight: $rule->weight,
            matched_label_ka: $this->matchedLabelKa,
            missing_label_ka: $this->missingLabelKa,
        );
    }

    private function resolveValue(BusinessProfileDto $profile): ?float
    {
        return match ($this->field) {
            'business_age_months' => (float) $profile->business_age_months,
            'annual_revenue_gel' => $profile->annual_revenue_gel,
            'employees_count' => (float) $profile->employees_count,
            'funding_amount_gel' => $profile->funding_amount_gel,
            'co_financing_pct' => $profile->co_financing_pct !== null ? (float) $profile->co_financing_pct : null,
            default => null,
        };
    }
}
