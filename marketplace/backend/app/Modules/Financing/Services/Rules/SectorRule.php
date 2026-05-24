<?php

declare(strict_types=1);

namespace App\Modules\Financing\Services\Rules;

use App\Modules\Financing\Dto\BusinessProfileDto;
use App\Modules\Financing\Dto\RuleEvaluationResult;
use App\Modules\Financing\Models\FundingProgramRule;

final class SectorRule implements RuleStrategy
{
    public function evaluate(BusinessProfileDto $profile, FundingProgramRule $rule): RuleEvaluationResult
    {
        $allowed = (array) ($rule->criteria['in'] ?? []);
        $passed = $allowed === [] || in_array($profile->sector, $allowed, true);
        $list = implode(', ', $allowed);

        return new RuleEvaluationResult(
            rule_type: $rule->rule_type,
            passed: $passed,
            is_required: $rule->is_required,
            weight: $rule->weight,
            matched_label_ka: "სექტორი ემთხვევა ({$list})",
            missing_label_ka: "თქვენი სექტორი არ შედის დაშვებულ ჩამონათვალში: {$list}",
        );
    }
}
