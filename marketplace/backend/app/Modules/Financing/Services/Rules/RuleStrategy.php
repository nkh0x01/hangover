<?php

declare(strict_types=1);

namespace App\Modules\Financing\Services\Rules;

use App\Modules\Financing\Dto\BusinessProfileDto;
use App\Modules\Financing\Dto\RuleEvaluationResult;
use App\Modules\Financing\Models\FundingProgramRule;

interface RuleStrategy
{
    public function evaluate(BusinessProfileDto $profile, FundingProgramRule $rule): RuleEvaluationResult;
}
