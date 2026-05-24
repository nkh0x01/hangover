<?php

declare(strict_types=1);

namespace App\Modules\Financing\Http\Controllers;

use App\Modules\Financing\Dto\BusinessProfileDto;
use App\Modules\Financing\Services\MatcherService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class RecommendationController extends Controller
{
    public function __construct(private readonly MatcherService $matcher) {}

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'sector' => ['required', 'string', 'max:32'],
            'region' => ['required', 'string', 'max:64'],
            'municipality' => ['nullable', 'string', 'max:120'],
            'business_age_months' => ['nullable', 'integer', 'min:0'],
            'annual_revenue_gel' => ['nullable', 'numeric', 'min:0'],
            'employees_count' => ['nullable', 'integer', 'min:0'],
            'funding_amount_gel' => ['nullable', 'numeric', 'min:0'],
            'co_financing_pct' => ['nullable', 'integer', 'min:0', 'max:100'],
            'purpose' => ['nullable', 'string', 'max:32'],
            'is_woman_owned' => ['nullable', 'boolean'],
            'is_youth_owned' => ['nullable', 'boolean'],
            'is_mountainous_region' => ['nullable', 'boolean'],
            'is_startup' => ['nullable', 'boolean'],
            'is_existing_business' => ['nullable', 'boolean'],
            'is_agriculture' => ['nullable', 'boolean'],
            'is_non_agriculture' => ['nullable', 'boolean'],
        ]);

        $profile = BusinessProfileDto::fromArray($data);
        $recommendations = $this->matcher->recommend($profile);

        return response()->json([
            'profile' => $profile->toArray(),
            'recommendations' => $recommendations->map(fn ($r) => $r->toArray())->all(),
            'note_ka' => 'სისტემა ავტომატურად არ აგზავნის განაცხადს — გეხმარება შესაბამისი პროგრამის და დოკუმენტების მომზადებაში.',
        ]);
    }
}
