<?php

declare(strict_types=1);

namespace App\Modules\Financing\Services;

use App\Modules\Financing\Dto\BusinessProfileDto;
use App\Modules\Financing\Dto\RuleEvaluationResult;
use App\Modules\Financing\Models\FundingProgram;
use App\Modules\Financing\Services\Rules\BooleanFlagRule;
use App\Modules\Financing\Services\Rules\PurposeRule;
use App\Modules\Financing\Services\Rules\RangeRule;
use App\Modules\Financing\Services\Rules\RegionRule;
use App\Modules\Financing\Services\Rules\RuleStrategy;
use App\Modules\Financing\Services\Rules\SectorRule;

class EligibilityEvaluator
{
    /**
     * Evaluate every rule of $program against $profile.
     *
     * @return list<RuleEvaluationResult>
     */
    public function evaluate(BusinessProfileDto $profile, FundingProgram $program): array
    {
        $results = [];
        foreach ($program->rules as $rule) {
            $strategy = $this->strategyFor($rule->rule_type);
            if ($strategy === null) {
                continue;
            }
            $results[] = $strategy->evaluate($profile, $rule);
        }

        return $results;
    }

    private function strategyFor(string $ruleType): ?RuleStrategy
    {
        return match ($ruleType) {
            'sector' => new SectorRule,
            'region' => new RegionRule,
            'business_age_min_months', 'business_age_max_months', 'business_age_range_months' => new RangeRule(
                field: 'business_age_months',
                matchedLabelKa: 'ბიზნესის ხნოვანება ემთხვევა მოთხოვნებს',
                missingLabelKa: 'ბიზნესის ხნოვანება არ ემთხვევა მოთხოვნებს',
            ),
            'revenue_min_gel', 'revenue_max_gel', 'revenue_range_gel' => new RangeRule(
                field: 'annual_revenue_gel',
                matchedLabelKa: 'წლიური ბრუნვა ემთხვევა მოთხოვნებს',
                missingLabelKa: 'წლიური ბრუნვა არ ჯდება დასაშვებ ჩარჩოში',
            ),
            'employees_min', 'employees_max', 'employees_range' => new RangeRule(
                field: 'employees_count',
                matchedLabelKa: 'თანამშრომელთა რაოდენობა შესაბამისობაშია',
                missingLabelKa: 'თანამშრომელთა რაოდენობა არ ჯდება მოთხოვნებში',
            ),
            'amount_min_gel', 'amount_max_gel', 'amount_range_gel' => new RangeRule(
                field: 'funding_amount_gel',
                matchedLabelKa: 'მოთხოვნილი თანხა ემთხვევა პროგრამის ჩარჩოს',
                missingLabelKa: 'მოთხოვნილი თანხა სცილდება პროგრამის ლიმიტს',
            ),
            'co_financing_max_pct' => new RangeRule(
                field: 'co_financing_pct',
                matchedLabelKa: 'თქვენი თანადაფინანსების შესაძლებლობა საკმარისია',
                missingLabelKa: 'საჭიროა მეტი თანადაფინანსება',
            ),
            'requires_woman_owned' => new BooleanFlagRule(
                field: 'is_woman_owned',
                matchedLabelKa: 'ქალის მფლობელობაში მყოფი ბიზნესი',
                missingLabelKa: 'პროგრამა მხოლოდ ქალი მფლობელის ბიზნესისთვისაა',
            ),
            'requires_youth_owned' => new BooleanFlagRule(
                field: 'is_youth_owned',
                matchedLabelKa: 'ახალგაზრდის (35-მდე) ბიზნესი',
                missingLabelKa: 'პროგრამა მხოლოდ 35-მდე ასაკის მფლობელისთვისაა',
            ),
            'requires_mountainous' => new BooleanFlagRule(
                field: 'is_mountainous_region',
                matchedLabelKa: 'მაღალმთიანი რეგიონის სტატუსი',
                missingLabelKa: 'პროგრამა მხოლოდ მაღალმთიანი რეგიონებისთვისაა',
            ),
            'requires_startup' => new BooleanFlagRule(
                field: 'is_startup',
                matchedLabelKa: 'სტარტაპის სტატუსი',
                missingLabelKa: 'პროგრამა მხოლოდ სტარტაპებისთვისაა',
            ),
            'requires_existing_business' => new BooleanFlagRule(
                field: 'is_existing_business',
                matchedLabelKa: 'არსებული ბიზნესი',
                missingLabelKa: 'პროგრამა მხოლოდ მოქმედი ბიზნესისთვისაა',
            ),
            'requires_agriculture' => new BooleanFlagRule(
                field: 'is_agriculture',
                matchedLabelKa: 'სოფლის მეურნეობის სექტორი',
                missingLabelKa: 'პროგრამა მხოლოდ აგრო-სექტორისთვისაა',
            ),
            'requires_non_agriculture' => new BooleanFlagRule(
                field: 'is_non_agriculture',
                matchedLabelKa: 'არა-აგრო სექტორი',
                missingLabelKa: 'პროგრამა მხოლოდ არა-აგრო სექტორისთვისაა',
            ),
            'purpose' => new PurposeRule,
            default => null,
        };
    }
}
