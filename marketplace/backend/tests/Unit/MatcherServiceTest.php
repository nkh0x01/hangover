<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Modules\Financing\Dto\BusinessProfileDto;
use App\Modules\Financing\Models\FundingProgram;
use App\Modules\Financing\Models\FundingProgramRule;
use App\Modules\Financing\Services\EligibilityEvaluator;
use App\Modules\Financing\Services\MatcherService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MatcherServiceTest extends TestCase
{
    use RefreshDatabase;

    private MatcherService $matcher;

    protected function setUp(): void
    {
        parent::setUp();
        $this->matcher = new MatcherService(new EligibilityEvaluator);
    }

    public function test_woman_owned_startup_matches_woman_program(): void
    {
        $this->seedProgram('Women grant', 'grants_gov_ge', 'grant', [
            ['rule_type' => 'requires_woman_owned', 'criteria' => ['value' => true], 'weight' => 50, 'is_required' => true],
        ]);
        $this->seedProgram('Generic grant', 'other', 'grant', [
            ['rule_type' => 'requires_existing_business', 'criteria' => ['value' => true], 'weight' => 10],
        ]);

        $recs = $this->matcher->recommend(BusinessProfileDto::fromArray([
            'sector' => 'cosmetics',
            'region' => 'tbilisi',
            'is_woman_owned' => true,
            'is_existing_business' => true,
        ]));

        $this->assertGreaterThanOrEqual(2, $recs->count());
        $this->assertSame('Women grant', $recs->first()->program->name_ka);
        $this->assertSame(100, $recs->first()->match_percentage);
    }

    public function test_required_rule_failure_excludes_program(): void
    {
        $this->seedProgram('Mountain-only', 'rda', 'grant', [
            ['rule_type' => 'requires_mountainous', 'criteria' => ['value' => true], 'weight' => 50, 'is_required' => true],
        ]);

        // Profile NOT mountainous — should be excluded entirely (only 1 required rule, 100% fail ratio).
        $recs = $this->matcher->recommend(BusinessProfileDto::fromArray([
            'sector' => 'cosmetics',
            'region' => 'tbilisi',
            'is_mountainous_region' => false,
        ]));

        $names = $recs->pluck('program.name_ka')->all();
        $this->assertNotContains('Mountain-only', $names);
    }

    public function test_amount_max_rule_passes_when_within_range(): void
    {
        $this->seedProgram('Cap 50k', 'other', 'grant', [
            ['rule_type' => 'amount_max_gel', 'criteria' => ['max' => 50000], 'weight' => 20, 'is_required' => true],
        ]);

        $recsOver = $this->matcher->recommend(BusinessProfileDto::fromArray([
            'sector' => 'cosmetics',
            'region' => 'tbilisi',
            'funding_amount_gel' => 100000,
        ]));
        $this->assertNotContains('Cap 50k', $recsOver->pluck('program.name_ka')->all());

        $recsUnder = $this->matcher->recommend(BusinessProfileDto::fromArray([
            'sector' => 'cosmetics',
            'region' => 'tbilisi',
            'funding_amount_gel' => 30000,
        ]));
        $this->assertContains('Cap 50k', $recsUnder->pluck('program.name_ka')->all());
    }

    public function test_business_age_range_filters_too_young(): void
    {
        $this->seedProgram('Mature only', 'other', 'subsidized_loan', [
            ['rule_type' => 'business_age_min_months', 'criteria' => ['min' => 24], 'weight' => 30, 'is_required' => true],
        ]);

        $tooYoung = $this->matcher->recommend(BusinessProfileDto::fromArray([
            'sector' => 'cosmetics',
            'region' => 'tbilisi',
            'business_age_months' => 6,
        ]));
        $this->assertNotContains('Mature only', $tooYoung->pluck('program.name_ka')->all());

        $oldEnough = $this->matcher->recommend(BusinessProfileDto::fromArray([
            'sector' => 'cosmetics',
            'region' => 'tbilisi',
            'business_age_months' => 36,
        ]));
        $this->assertContains('Mature only', $oldEnough->pluck('program.name_ka')->all());
    }

    public function test_ranked_by_match_percentage_then_max_amount(): void
    {
        $this->seedProgram('Big perfect', 'other', 'grant', [
            ['rule_type' => 'sector', 'criteria' => ['in' => ['cosmetics']], 'weight' => 10],
        ], maxAmount: 500000);
        $this->seedProgram('Small perfect', 'other', 'grant', [
            ['rule_type' => 'sector', 'criteria' => ['in' => ['cosmetics']], 'weight' => 10],
        ], maxAmount: 10000);

        $recs = $this->matcher->recommend(BusinessProfileDto::fromArray([
            'sector' => 'cosmetics',
            'region' => 'tbilisi',
        ]));

        // Both at 100%, ranked by max_amount DESC.
        $this->assertSame('Big perfect', $recs->first()->program->name_ka);
    }

    public function test_returns_recommendation_for_program_with_no_rules(): void
    {
        $this->seedProgram('Open call', 'other', 'grant', []);

        $recs = $this->matcher->recommend(BusinessProfileDto::fromArray([
            'sector' => 'cosmetics',
            'region' => 'tbilisi',
        ]));

        $this->assertCount(1, $recs);
        $this->assertSame(50, $recs->first()->match_percentage);
    }

    /**
     * @param  list<array<string,mixed>>  $rules
     */
    private function seedProgram(string $name, string $provider, string $type, array $rules, ?float $maxAmount = null): FundingProgram
    {
        $p = FundingProgram::create([
            'slug' => 'p-'.uniqid(),
            'name_ka' => $name,
            'provider' => $provider,
            'program_type' => $type,
            'summary_ka' => '—',
            'description_ka' => '—',
            'max_amount_gel' => $maxAmount,
            'is_active' => true,
            'is_demo' => true,
        ]);

        foreach ($rules as $r) {
            FundingProgramRule::create([
                'funding_program_id' => $p->id,
                'rule_type' => $r['rule_type'],
                'criteria' => $r['criteria'],
                'weight' => $r['weight'] ?? 10,
                'is_required' => $r['is_required'] ?? false,
            ]);
        }

        return $p;
    }
}
