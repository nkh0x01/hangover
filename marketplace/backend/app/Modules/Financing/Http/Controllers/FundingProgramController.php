<?php

declare(strict_types=1);

namespace App\Modules\Financing\Http\Controllers;

use App\Modules\Financing\Models\FundingProgram;
use App\Modules\Financing\Services\ChecklistBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class FundingProgramController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $q = FundingProgram::query()->active();
        if ($request->filled('provider')) {
            $q->where('provider', $request->string('provider'));
        }
        if ($request->filled('program_type')) {
            $q->where('program_type', $request->string('program_type'));
        }

        return response()->json($q->orderBy('name_ka')->paginate(24));
    }

    public function show(FundingProgram $program, ChecklistBuilder $builder): JsonResponse
    {
        $program->load('rules');

        return response()->json([
            'data' => $program,
            'checklist' => $builder->build($program),
        ]);
    }
}
