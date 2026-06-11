<?php

declare(strict_types=1);

namespace App\Modules\Financing\Services\Rules;

use App\Modules\Financing\Dto\BusinessProfileDto;
use App\Modules\Financing\Dto\RuleEvaluationResult;
use App\Modules\Financing\Models\FundingProgramRule;

final class PurposeRule implements RuleStrategy
{
    public function evaluate(BusinessProfileDto $profile, FundingProgramRule $rule): RuleEvaluationResult
    {
        $allowed = (array) ($rule->criteria['in'] ?? []);
        $passed = $profile->purpose === null
            || $allowed === []
            || in_array($profile->purpose, $allowed, true);

        return new RuleEvaluationResult(
            rule_type: $rule->rule_type,
            passed: $passed,
            is_required: $rule->is_required,
            weight: $rule->weight,
            matched_label_ka: 'დაფინანსების მიზანი ემთხვევა პროგრამის ფარგლებს',
            missing_label_ka: 'პროგრამა მხოლოდ კონკრეტული მიზნებისთვის ვრცელდება',
        );
    }
}
