<?php

declare(strict_types=1);

namespace App\Modules\Financing\Services;

use App\Modules\Financing\Dto\BusinessProfileDto;
use App\Modules\Financing\Dto\RecommendationDto;
use App\Modules\Financing\Models\FundingProgram;
use Illuminate\Support\Collection;

class MatcherService
{
    /**
     * Penalty subtracted from match_percentage per required-rule failure.
     */
    public const REQUIRED_FAIL_PENALTY = 20;

    /**
     * Programs are dropped from results when more than this percentage of
     * required rules fail.
     */
    public const MAX_REQUIRED_FAIL_RATIO = 0.5;

    public function __construct(
        private readonly EligibilityEvaluator $evaluator,
    ) {}

    /**
     * Rank active funding programs against $profile.
     *
     * @return Collection<int, RecommendationDto>
     */
    public function recommend(BusinessProfileDto $profile, int $limit = 20): Collection
    {
        $programs = FundingProgram::query()
            ->active()
            ->with('rules')
            ->get();

        $recs = $programs
            ->map(fn (FundingProgram $p) => $this->score($profile, $p))
            ->filter()
            ->sortByDesc(fn (RecommendationDto $r) => [$r->match_percentage, (float) ($r->program->max_amount_gel ?? 0)])
            ->values()
            ->take($limit);

        /** @var Collection<int, RecommendationDto> $recs */
        return $recs;
    }

    private function score(BusinessProfileDto $profile, FundingProgram $program): ?RecommendationDto
    {
        $results = $this->evaluator->evaluate($profile, $program);

        if ($results === []) {
            return new RecommendationDto(
                program: $program,
                match_percentage: 50,
                matched_rules: ['პროგრამას არ აქვს კონკრეტული მოთხოვნები — ღია განაცხადი'],
                missing_requirements: [],
                suggested_next_step_ka: 'გადახედე პროგრამის დეტალებს და გადადი ოფიციალურ საიტზე',
            );
        }

        $requiredTotal = 0;
        $requiredFailed = 0;
        $totalWeight = 0;
        $passedWeight = 0;
        $matched = [];
        $missing = [];

        foreach ($results as $r) {
            $totalWeight += $r->weight;
            if ($r->passed) {
                $passedWeight += $r->weight;
                $matched[] = $r->matched_label_ka;
            } else {
                $missing[] = $r->missing_label_ka;
            }

            if ($r->is_required) {
                $requiredTotal++;
                if (! $r->passed) {
                    $requiredFailed++;
                }
            }
        }

        if ($requiredTotal > 0 && ($requiredFailed / $requiredTotal) > self::MAX_REQUIRED_FAIL_RATIO) {
            return null;
        }

        $percentage = $totalWeight > 0
            ? (int) round(($passedWeight / $totalWeight) * 100)
            : 0;

        $percentage = max(0, $percentage - ($requiredFailed * self::REQUIRED_FAIL_PENALTY));

        return new RecommendationDto(
            program: $program,
            match_percentage: $percentage,
            matched_rules: $matched,
            missing_requirements: $missing,
            suggested_next_step_ka: $this->suggestedNextStep($percentage, $missing),
        );
    }

    /**
     * @param  list<string>  $missing
     */
    private function suggestedNextStep(int $percentage, array $missing): string
    {
        if ($percentage >= 80) {
            return 'შენახე პროგრამა, მოამზადე საბუთები და გადადი ოფიციალურ განაცხადზე';
        }
        if ($percentage >= 50) {
            return 'პროგრამა ნაწილობრივ შეესაბამება — ნახე გამოტოვებული მოთხოვნები და მოამზადე დაკარგული საბუთები';
        }

        return 'ალბათობა დაბალია, თუმცა შეგიძლია გადახედე პროგრამის დეტალურ პირობებს';
    }
}
