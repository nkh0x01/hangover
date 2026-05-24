<?php

declare(strict_types=1);

namespace App\Modules\Financing\Dto;

/**
 * Snapshot of the small entrepreneur's profile used to match against
 * funding programs. Captured from the questionnaire wizard.
 */
final class BusinessProfileDto
{
    public function __construct(
        public readonly string $sector,
        public readonly string $region,
        public readonly ?string $municipality = null,
        public readonly int $business_age_months = 0,
        public readonly ?float $annual_revenue_gel = null,
        public readonly int $employees_count = 0,
        public readonly ?float $funding_amount_gel = null,
        public readonly ?int $co_financing_pct = null,
        public readonly ?string $purpose = null,
        public readonly bool $is_woman_owned = false,
        public readonly bool $is_youth_owned = false,
        public readonly bool $is_mountainous_region = false,
        public readonly bool $is_startup = false,
        public readonly bool $is_existing_business = true,
        public readonly bool $is_agriculture = false,
        public readonly bool $is_non_agriculture = true,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            sector: (string) ($data['sector'] ?? 'other'),
            region: (string) ($data['region'] ?? 'tbilisi'),
            municipality: $data['municipality'] ?? null,
            business_age_months: (int) ($data['business_age_months'] ?? 0),
            annual_revenue_gel: isset($data['annual_revenue_gel']) ? (float) $data['annual_revenue_gel'] : null,
            employees_count: (int) ($data['employees_count'] ?? 0),
            funding_amount_gel: isset($data['funding_amount_gel']) ? (float) $data['funding_amount_gel'] : null,
            co_financing_pct: isset($data['co_financing_pct']) ? (int) $data['co_financing_pct'] : null,
            purpose: $data['purpose'] ?? null,
            is_woman_owned: (bool) ($data['is_woman_owned'] ?? false),
            is_youth_owned: (bool) ($data['is_youth_owned'] ?? false),
            is_mountainous_region: (bool) ($data['is_mountainous_region'] ?? false),
            is_startup: (bool) ($data['is_startup'] ?? false),
            is_existing_business: (bool) ($data['is_existing_business'] ?? true),
            is_agriculture: (bool) ($data['is_agriculture'] ?? false),
            is_non_agriculture: (bool) ($data['is_non_agriculture'] ?? true),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'sector' => $this->sector,
            'region' => $this->region,
            'municipality' => $this->municipality,
            'business_age_months' => $this->business_age_months,
            'annual_revenue_gel' => $this->annual_revenue_gel,
            'employees_count' => $this->employees_count,
            'funding_amount_gel' => $this->funding_amount_gel,
            'co_financing_pct' => $this->co_financing_pct,
            'purpose' => $this->purpose,
            'is_woman_owned' => $this->is_woman_owned,
            'is_youth_owned' => $this->is_youth_owned,
            'is_mountainous_region' => $this->is_mountainous_region,
            'is_startup' => $this->is_startup,
            'is_existing_business' => $this->is_existing_business,
            'is_agriculture' => $this->is_agriculture,
            'is_non_agriculture' => $this->is_non_agriculture,
        ];
    }
}
