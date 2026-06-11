<?php

declare(strict_types=1);

namespace App\Modules\Financing\Dto;

final class RuleEvaluationResult
{
    public function __construct(
        public readonly string $rule_type,
        public readonly bool $passed,
        public readonly bool $is_required,
        public readonly int $weight,
        public readonly string $matched_label_ka,
        public readonly string $missing_label_ka,
    ) {}
}
