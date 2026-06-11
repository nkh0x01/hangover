<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Modules\Financing\Dto\BusinessProfileDto;
use App\Modules\Financing\Models\FundingApplication;
use App\Modules\Financing\Models\FundingProgram;
use App\Modules\Financing\Services\ChecklistBuilder;
use App\Modules\Financing\Services\MatcherService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FinancingWebController extends Controller
{
    public function landing(): View
    {
        return view('pages.financing-landing');
    }

    public function questionnaire(): View
    {
        return view('pages.financing-questionnaire');
    }

    public function recommendations(Request $request, MatcherService $matcher): View
    {
        $data = $request->validate([
            'sector' => ['required', 'string'],
            'region' => ['required', 'string'],
            'business_age_months' => ['nullable', 'integer'],
            'annual_revenue_gel' => ['nullable', 'numeric'],
            'employees_count' => ['nullable', 'integer'],
            'funding_amount_gel' => ['nullable', 'numeric'],
            'co_financing_pct' => ['nullable', 'integer'],
            'purpose' => ['nullable', 'string'],
            'is_woman_owned' => ['nullable', 'boolean'],
            'is_youth_owned' => ['nullable', 'boolean'],
            'is_mountainous_region' => ['nullable', 'boolean'],
            'is_startup' => ['nullable', 'boolean'],
            'is_existing_business' => ['nullable', 'boolean'],
            'is_agriculture' => ['nullable', 'boolean'],
        ]);

        // Treat absence of flags as false; absence of is_non_agriculture inferred from is_agriculture.
        $data['is_non_agriculture'] = ! ($data['is_agriculture'] ?? false);

        $profile = BusinessProfileDto::fromArray($data);
        $recs = $matcher->recommend($profile, 15);

        return view('pages.financing-recommendations', [
            'recommendations' => $recs->map(fn ($r) => $r->toArray())->all(),
        ]);
    }

    public function programs(): View
    {
        return view('pages.financing-programs', [
            'programs' => FundingProgram::active()->orderBy('name_ka')->paginate(20),
        ]);
    }

    public function program(FundingProgram $program, ChecklistBuilder $builder): View
    {
        return view('pages.financing-program-show', [
            'program' => $program,
            'checklist' => $builder->build($program),
        ]);
    }

    public function startApplication(Request $request): RedirectResponse
    {
        $data = $request->validate(['funding_program_id' => ['required', 'integer', 'exists:funding_programs,id']]);

        FundingApplication::firstOrCreate(
            [
                'user_id' => $request->user()->id,
                'funding_program_id' => $data['funding_program_id'],
                'status' => 'draft',
            ],
            ['business_profile_snapshot' => []],
        );

        return back()->with('status', 'განაცხადის დრაფტი შეიქმნა');
    }
}
