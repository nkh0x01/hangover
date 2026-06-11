<?php

declare(strict_types=1);

namespace App\Modules\Financing\Services;

use App\Modules\Financing\Models\FundingProgram;

/**
 * Generates a Georgian-language document checklist for a given funding program
 * based on its program_type and provider. The checklist guides the small
 * entrepreneur on what to prepare BEFORE opening the official application URL.
 */
class ChecklistBuilder
{
    /**
     * @return list<array{key: string, label_ka: string, required: bool}>
     */
    public function build(FundingProgram $program): array
    {
        $base = [
            ['key' => 'id_copy', 'label_ka' => 'პირადობის მოწმობის ასლი', 'required' => true],
            ['key' => 'registration_extract', 'label_ka' => 'რეგისტრაციის ამონაწერი (იპ/შპს)', 'required' => true],
            ['key' => 'business_plan', 'label_ka' => 'მოკლე ბიზნეს გეგმა / აღწერა', 'required' => true],
        ];

        $byType = match ($program->program_type) {
            'grant' => [
                ['key' => 'budget', 'label_ka' => 'პროექტის ბიუჯეტი', 'required' => true],
                ['key' => 'co_financing_proof', 'label_ka' => 'თანადაფინანსების დადასტურება (თუ ვრცელდება)', 'required' => false],
            ],
            'subsidized_loan' => [
                ['key' => 'financial_statement', 'label_ka' => 'ბოლო 1 წლის ფინანსური ანგარიში', 'required' => true],
                ['key' => 'collateral_docs', 'label_ka' => 'უზრუნველყოფის დოკუმენტი (თუ ვრცელდება)', 'required' => false],
            ],
            'training', 'coaching' => [
                ['key' => 'cv', 'label_ka' => 'მფლობელის CV', 'required' => true],
            ],
            default => [],
        };

        $byProvider = match ($program->provider) {
            'enterprise_georgia' => [
                ['key' => 'tax_certificate', 'label_ka' => 'საგადასახადო ცნობა', 'required' => true],
            ],
            'rda' => [
                ['key' => 'land_certificate', 'label_ka' => 'სასოფლო-სამეურნეო მიწის ცნობა', 'required' => false],
            ],
            'gita' => [
                ['key' => 'pitch_deck', 'label_ka' => 'პრეზენტაცია (pitch deck)', 'required' => true],
            ],
            default => [],
        };

        return array_values(array_merge($base, $byType, $byProvider));
    }
}
