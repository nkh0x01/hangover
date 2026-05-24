<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Financing\Models\FundingProgram;
use App\Modules\Financing\Models\FundingProgramRule;
use Illuminate\Database\Seeder;

/**
 * Demo funding programs. All entries are flagged `is_demo=true` and MUST be
 * verified against the official source before being shown without the demo
 * notice. Rule criteria represent best-effort eligibility encoding for
 * matcher demonstration purposes.
 */
class FundingProgramsSeeder extends Seeder
{
    public function run(): void
    {
        $programs = [
            [
                'slug' => 'enterprise-georgia-produce-in-georgia',
                'name_ka' => 'აწარმოე საქართველოში — ინდუსტრიული კომპონენტი',
                'provider' => 'enterprise_georgia',
                'program_type' => 'mixed',
                'summary_ka' => 'საპროცენტო განაკვეთის თანადაფინანსება და გრანტი მცირე და საშუალო მწარმოებლებისთვის.',
                'description_ka' => 'პროგრამა ეხმარება საქართველოში მოქმედ მცირე და საშუალო მწარმოებლებს — საპროცენტო თანადაფინანსება, გრანტი ძირითადი ფონდის შესაძენად, ტექნიკური დახმარება.',
                'min_amount_gel' => 50000,
                'max_amount_gel' => 5000000,
                'co_financing_required_pct' => 20,
                'application_url' => 'https://www.enterprisegeorgia.gov.ge/ka',
                'rules' => [
                    ['rule_type' => 'sector', 'criteria' => ['in' => ['food_and_drink', 'cosmetics', 'fashion', 'home_and_interior', 'crafts', 'jewelry', 'print_and_design', 'kids', 'eco']], 'weight' => 30, 'is_required' => true],
                    ['rule_type' => 'requires_non_agriculture', 'criteria' => ['value' => true], 'weight' => 20, 'is_required' => true],
                    ['rule_type' => 'amount_max_gel', 'criteria' => ['max' => 5000000], 'weight' => 15],
                    ['rule_type' => 'business_age_min_months', 'criteria' => ['min' => 6], 'weight' => 10],
                    ['rule_type' => 'co_financing_max_pct', 'criteria' => ['min' => 20], 'weight' => 15],
                ],
            ],
            [
                'slug' => 'enterprise-georgia-micro',
                'name_ka' => 'აწარმოე საქართველოში — მიკრო და მცირე ბიზნესი',
                'provider' => 'enterprise_georgia',
                'program_type' => 'grant',
                'summary_ka' => 'მცირე გრანტი მიკრო ბიზნესის დასაწყებად ან გასაფართოებლად.',
                'description_ka' => 'მიკრო-გრანტი ფიზიკური პირებისთვის და ინდივიდუალური მეწარმეებისთვის — ბიზნესის დასაწყებად ან გასაფართოებლად საქართველოს რეგიონებში.',
                'min_amount_gel' => 5000,
                'max_amount_gel' => 30000,
                'co_financing_required_pct' => 20,
                'application_url' => 'https://www.enterprisegeorgia.gov.ge/ka',
                'rules' => [
                    ['rule_type' => 'amount_max_gel', 'criteria' => ['max' => 30000], 'weight' => 20, 'is_required' => true],
                    ['rule_type' => 'employees_max', 'criteria' => ['max' => 30], 'weight' => 15],
                    ['rule_type' => 'revenue_max_gel', 'criteria' => ['max' => 500000], 'weight' => 15],
                ],
            ],
            [
                'slug' => 'rda-agro-credit',
                'name_ka' => 'RDA — შეღავათიანი აგრო-კრედიტი',
                'provider' => 'rda',
                'program_type' => 'subsidized_loan',
                'summary_ka' => 'სოფლის მეურნეობის სუბსიდირებული სესხი ფერმერებისთვის.',
                'description_ka' => 'სოფლის მეურნეობის სააგენტოს შეღავათიანი აგრო-კრედიტი — საპროცენტო განაკვეთის სუბსიდია მცირე და საშუალო ფერმერებისთვის.',
                'min_amount_gel' => 5000,
                'max_amount_gel' => 1500000,
                'application_url' => 'https://rda.gov.ge',
                'rules' => [
                    ['rule_type' => 'requires_agriculture', 'criteria' => ['value' => true], 'weight' => 40, 'is_required' => true],
                    ['rule_type' => 'sector', 'criteria' => ['in' => ['agriculture', 'food_and_drink']], 'weight' => 20],
                    ['rule_type' => 'amount_max_gel', 'criteria' => ['max' => 1500000], 'weight' => 15],
                ],
            ],
            [
                'slug' => 'rda-mountainous-support',
                'name_ka' => 'RDA — მაღალმთიანი რეგიონების მხარდაჭერა',
                'provider' => 'rda',
                'program_type' => 'grant',
                'summary_ka' => 'გრანტი მაღალმთიანი რეგიონების მცირე ბიზნესისთვის.',
                'description_ka' => 'მაღალმთიანი რეგიონების მცირე მეწარმეებისთვის გათვალისწინებული გრანტი — საქმის დასაწყებად ან გასაფართოებლად.',
                'min_amount_gel' => 5000,
                'max_amount_gel' => 100000,
                'co_financing_required_pct' => 10,
                'application_url' => 'https://rda.gov.ge',
                'rules' => [
                    ['rule_type' => 'requires_mountainous', 'criteria' => ['value' => true], 'weight' => 40, 'is_required' => true],
                    ['rule_type' => 'amount_max_gel', 'criteria' => ['max' => 100000], 'weight' => 20],
                    ['rule_type' => 'co_financing_max_pct', 'criteria' => ['min' => 10], 'weight' => 10],
                ],
            ],
            [
                'slug' => 'gita-startup-georgia',
                'name_ka' => 'GITA — Startup Georgia',
                'provider' => 'gita',
                'program_type' => 'grant',
                'summary_ka' => 'სტარტაპებისთვის სამატჩი გრანტი იდეიდან პროტოტიპამდე.',
                'description_ka' => 'საქართველოს ინოვაციებისა და ტექნოლოგიების სააგენტოს Startup Georgia პროგრამა — სამატჩი გრანტი ინოვაციური სტარტაპებისთვის.',
                'min_amount_gel' => 15000,
                'max_amount_gel' => 100000,
                'co_financing_required_pct' => 10,
                'application_url' => 'https://gita.gov.ge',
                'rules' => [
                    ['rule_type' => 'requires_startup', 'criteria' => ['value' => true], 'weight' => 35, 'is_required' => true],
                    ['rule_type' => 'amount_max_gel', 'criteria' => ['max' => 100000], 'weight' => 15],
                    ['rule_type' => 'business_age_max_months', 'criteria' => ['max' => 36], 'weight' => 15],
                ],
            ],
            [
                'slug' => 'grants-gov-ge-women',
                'name_ka' => 'grants.gov.ge — ქალთა მეწარმეობის გრანტი',
                'provider' => 'grants_gov_ge',
                'program_type' => 'grant',
                'summary_ka' => 'მიზნობრივი გრანტი ქალი მეწარმეებისთვის.',
                'description_ka' => 'მცირე გრანტი ქალის მფლობელობაში მყოფი ბიზნესისთვის — საქართველოს ნებისმიერი რეგიონიდან.',
                'min_amount_gel' => 5000,
                'max_amount_gel' => 50000,
                'application_url' => 'https://grants.gov.ge',
                'rules' => [
                    ['rule_type' => 'requires_woman_owned', 'criteria' => ['value' => true], 'weight' => 45, 'is_required' => true],
                    ['rule_type' => 'amount_max_gel', 'criteria' => ['max' => 50000], 'weight' => 20],
                ],
            ],
            [
                'slug' => 'grants-gov-ge-youth',
                'name_ka' => 'grants.gov.ge — ახალგაზრდა მეწარმის გრანტი',
                'provider' => 'grants_gov_ge',
                'program_type' => 'grant',
                'summary_ka' => '35 წლამდე მეწარმეებისთვის გრანტი.',
                'description_ka' => 'მცირე გრანტი 35 წლამდე ახალგაზრდა მფლობელის ბიზნესისთვის.',
                'min_amount_gel' => 5000,
                'max_amount_gel' => 40000,
                'application_url' => 'https://grants.gov.ge',
                'rules' => [
                    ['rule_type' => 'requires_youth_owned', 'criteria' => ['value' => true], 'weight' => 45, 'is_required' => true],
                    ['rule_type' => 'amount_max_gel', 'criteria' => ['max' => 40000], 'weight' => 20],
                ],
            ],
            [
                'slug' => 'eu4business-craft-export',
                'name_ka' => 'EU4Business — ხელნაკეთობის ექსპორტი (demo)',
                'provider' => 'eu4business',
                'program_type' => 'training',
                'summary_ka' => 'ექსპორტისთვის მზადება ხელნაკეთობის მცირე ბიზნესისთვის.',
                'description_ka' => 'EU4Business პროგრამის დემო ჩანაწერი — ხელნაკეთობის მცირე ბიზნესისთვის გასაყიდი ექსპორტისთვის მზადება, ტრენინგი და კონსულტაცია.',
                'min_amount_gel' => 0,
                'max_amount_gel' => 20000,
                'application_url' => 'https://eu4business.eu',
                'rules' => [
                    ['rule_type' => 'sector', 'criteria' => ['in' => ['crafts', 'jewelry', 'fashion', 'home_and_interior']], 'weight' => 30, 'is_required' => true],
                    ['rule_type' => 'business_age_min_months', 'criteria' => ['min' => 12], 'weight' => 15],
                ],
            ],
            [
                'slug' => 'tbc-woman-loan',
                'name_ka' => 'TBC ბანკი — ქალთა ბიზნეს კრედიტი (demo)',
                'provider' => 'other',
                'program_type' => 'subsidized_loan',
                'summary_ka' => 'შეღავათიანი სესხი ქალთა ბიზნესისთვის.',
                'description_ka' => 'TBC ბანკის შეღავათიანი სესხის დემო ჩანაწერი ქალის მფლობელობაში მყოფი მცირე ბიზნესისთვის.',
                'min_amount_gel' => 5000,
                'max_amount_gel' => 200000,
                'application_url' => 'https://www.tbcbank.ge',
                'rules' => [
                    ['rule_type' => 'requires_woman_owned', 'criteria' => ['value' => true], 'weight' => 35, 'is_required' => true],
                    ['rule_type' => 'amount_max_gel', 'criteria' => ['max' => 200000], 'weight' => 15],
                ],
            ],
            [
                'slug' => 'bog-solo-entrepreneur',
                'name_ka' => 'საქართველოს ბანკი — ინდმეწარმის სესხი (demo)',
                'provider' => 'other',
                'program_type' => 'subsidized_loan',
                'summary_ka' => 'შეღავათიანი სესხი ინდივიდუალური მეწარმეებისთვის.',
                'description_ka' => 'საქართველოს ბანკის შეღავათიანი სესხის დემო ჩანაწერი ინდმეწარმეებისთვის.',
                'min_amount_gel' => 3000,
                'max_amount_gel' => 100000,
                'application_url' => 'https://bankofgeorgia.ge',
                'rules' => [
                    ['rule_type' => 'amount_max_gel', 'criteria' => ['max' => 100000], 'weight' => 20],
                    ['rule_type' => 'business_age_min_months', 'criteria' => ['min' => 6], 'weight' => 15],
                ],
            ],
            [
                'slug' => 'gita-innovation-grant',
                'name_ka' => 'GITA — ინოვაციური სამატჩი გრანტი',
                'provider' => 'gita',
                'program_type' => 'grant',
                'summary_ka' => 'სამატჩი გრანტი ინოვაციური პროდუქტის შემუშავებისთვის.',
                'description_ka' => 'GITA-ს ინოვაციური სამატჩი გრანტი — ტექნოლოგიური და კრეატიული ინდუსტრიების მცირე ბიზნესისთვის.',
                'min_amount_gel' => 25000,
                'max_amount_gel' => 250000,
                'co_financing_required_pct' => 20,
                'application_url' => 'https://gita.gov.ge',
                'rules' => [
                    ['rule_type' => 'amount_max_gel', 'criteria' => ['max' => 250000], 'weight' => 15],
                    ['rule_type' => 'sector', 'criteria' => ['in' => ['print_and_design', 'crafts', 'kids', 'fashion', 'eco', 'cosmetics']], 'weight' => 20],
                    ['rule_type' => 'co_financing_max_pct', 'criteria' => ['min' => 20], 'weight' => 15, 'is_required' => true],
                ],
            ],
            [
                'slug' => 'rda-agro-insurance',
                'name_ka' => 'RDA — აგრო-დაზღვევის თანადაფინანსება',
                'provider' => 'rda',
                'program_type' => 'mixed',
                'summary_ka' => 'სოფლის მეურნეობის დაზღვევის სუბსიდია.',
                'description_ka' => 'სოფლის მეურნეობის სააგენტოს დაზღვევის თანადაფინანსების პროგრამა — ფერმერებისთვის ბუნებრივი რისკისგან დაცვა.',
                'min_amount_gel' => 0,
                'max_amount_gel' => 50000,
                'application_url' => 'https://rda.gov.ge',
                'rules' => [
                    ['rule_type' => 'requires_agriculture', 'criteria' => ['value' => true], 'weight' => 45, 'is_required' => true],
                ],
            ],
        ];

        foreach ($programs as $p) {
            $rules = $p['rules'];
            unset($p['rules']);

            $program = FundingProgram::updateOrCreate(
                ['slug' => $p['slug']],
                $p + [
                    'description_ka' => $p['description_ka'],
                    'is_active' => true,
                    'is_demo' => true,
                    'closes_at' => now()->addMonths(6)->toDateString(),
                ],
            );

            $program->rules()->delete();
            foreach ($rules as $r) {
                FundingProgramRule::create([
                    'funding_program_id' => $program->id,
                    'rule_type' => $r['rule_type'],
                    'criteria' => $r['criteria'],
                    'weight' => $r['weight'] ?? 10,
                    'is_required' => $r['is_required'] ?? false,
                ]);
            }
        }
    }
}
