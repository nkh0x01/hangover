<?php

declare(strict_types=1);

namespace App\Modules\Financing\Services\Rules;

use App\Modules\Financing\Dto\BusinessProfileDto;
use App\Modules\Financing\Dto\RuleEvaluationResult;
use App\Modules\Financing\Models\FundingProgramRule;

final class BooleanFlagRule implements RuleStrategy
{
    public function __construct(
        private readonly string $field,
        private readonly string $matchedLabelKa,
        private readonly string $missingLabelKa,
    ) {}

    public function evaluate(BusinessProfileDto $profile, FundingProgramRule $rule): RuleEvaluationResult
    {
        $expected = (bool) ($rule->criteria['value'] ?? true);
        $actual = $this->resolveValue($profile);
        $passed = $actual === $expected;

        return new RuleEvaluationResult(
            rule_type: $rule->rule_type,
            passed: $passed,
            is_required: $rule->is_required,
            weight: $rule->weight,
            matched_label_ka: $this->matchedLabelKa,
            missing_label_ka: $this->missingLabelKa,
        );
    }

    private function resolveValue(BusinessProfileDto $profile): bool
    {
        return match ($this->field) {
            'is_woman_owned' => $profile->is_woman_owned,
            'is_youth_owned' => $profile->is_youth_owned,
            'is_mountainous_region' => $profile->is_mountainous_region,
            'is_startup' => $profile->is_startup,
            'is_existing_business' => $profile->is_existing_business,
            'is_agriculture' => $profile->is_agriculture,
            'is_non_agriculture' => $profile->is_non_agriculture,
            default => false,
        };
    }
}
