<?php

declare(strict_types=1);

namespace App\Modules\Financing\Http\Controllers;

use App\Modules\Financing\Models\FundingApplication;
use App\Modules\Financing\Models\FundingProgram;
use App\Modules\Financing\Models\FundingSavedProgram;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class FundingApplicationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $items = FundingApplication::query()
            ->where('user_id', $request->user()->id)
            ->with(['program', 'consultant'])
            ->latest()
            ->paginate(20);

        return response()->json($items);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'funding_program_id' => ['required', 'integer', 'exists:funding_programs,id'],
            'amount_requested_gel' => ['nullable', 'numeric', 'min:0'],
            'purpose_ka' => ['nullable', 'string', 'max:2000'],
            'business_profile_snapshot' => ['nullable', 'array'],
        ]);

        $user = $request->user();
        $program = FundingProgram::findOrFail($data['funding_program_id']);

        $app = FundingApplication::create([
            'user_id' => $user->id,
            'seller_id' => $user->seller?->id,
            'funding_program_id' => $program->id,
            'status' => 'draft',
            'amount_requested_gel' => $data['amount_requested_gel'] ?? null,
            'purpose_ka' => $data['purpose_ka'] ?? null,
            'business_profile_snapshot' => $data['business_profile_snapshot'] ?? [],
        ]);

        return response()->json([
            'data' => $app,
            'message_ka' => 'განაცხადის დრაფტი შეიქმნა — მოამზადე საბუთები',
        ], 201);
    }

    public function show(Request $request, FundingApplication $application): JsonResponse
    {
        abort_unless($application->user_id === $request->user()->id, 403);
        $application->load(['program', 'documents', 'consultantNotes.consultant']);

        return response()->json(['data' => $application]);
    }

    public function requestConsultant(Request $request, FundingApplication $application): JsonResponse
    {
        abort_unless($application->user_id === $request->user()->id, 403);

        $application->update(['status' => 'in_consultation']);

        return response()->json([
            'ok' => true,
            'message_ka' => 'მოთხოვნა გადაიგზავნა კონსულტანტთან',
        ]);
    }

    public function saveProgram(Request $request): JsonResponse
    {
        $data = $request->validate([
            'funding_program_id' => ['required', 'integer', 'exists:funding_programs,id'],
            'match_percentage' => ['nullable', 'integer', 'min:0', 'max:100'],
        ]);

        $saved = FundingSavedProgram::updateOrCreate(
            ['user_id' => $request->user()->id, 'funding_program_id' => $data['funding_program_id']],
            ['match_percentage' => $data['match_percentage'] ?? 0],
        );

        return response()->json([
            'data' => $saved,
            'message_ka' => 'პროგრამა შენახულია',
        ], 201);
    }

    public function savedPrograms(Request $request): JsonResponse
    {
        $items = FundingSavedProgram::query()
            ->where('user_id', $request->user()->id)
            ->with('program')
            ->latest()
            ->paginate(20);

        return response()->json($items);
    }
}
