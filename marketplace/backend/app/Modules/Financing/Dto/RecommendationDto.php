<?php

declare(strict_types=1);

namespace App\Modules\Financing\Dto;

use App\Modules\Financing\Models\FundingProgram;

final class RecommendationDto
{
    /**
     * @param  list<string>  $matched_rules
     * @param  list<string>  $missing_requirements
     */
    public function __construct(
        public readonly FundingProgram $program,
        public readonly int $match_percentage,
        public readonly array $matched_rules,
        public readonly array $missing_requirements,
        public readonly string $suggested_next_step_ka,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'program' => [
                'id' => $this->program->id,
                'slug' => $this->program->slug,
                'name_ka' => $this->program->name_ka,
                'provider' => $this->program->provider,
                'program_type' => $this->program->program_type,
                'summary_ka' => $this->program->summary_ka,
                'min_amount_gel' => $this->program->min_amount_gel,
                'max_amount_gel' => $this->program->max_amount_gel,
                'co_financing_required_pct' => $this->program->co_financing_required_pct,
                'application_url' => $this->program->application_url,
                'closes_at' => $this->program->closes_at?->toDateString(),
                'is_demo' => $this->program->is_demo,
            ],
            'match_percentage' => $this->match_percentage,
            'matched_rules' => $this->matched_rules,
            'missing_requirements' => $this->missing_requirements,
            'suggested_next_step_ka' => $this->suggested_next_step_ka,
        ];
    }
}
