<?php

declare(strict_types=1);

use App\Modules\Financing\Http\Controllers\FundingApplicationController;
use App\Modules\Financing\Http\Controllers\FundingProgramController;
use App\Modules\Financing\Http\Controllers\RecommendationController;
use Illuminate\Support\Facades\Route;

Route::prefix('financing')->group(function () {
    Route::get('programs', [FundingProgramController::class, 'index']);
    Route::get('programs/{program:slug}', [FundingProgramController::class, 'show']);
    Route::post('recommendations', [RecommendationController::class, 'store']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('saved', [FundingApplicationController::class, 'saveProgram']);
        Route::get('saved', [FundingApplicationController::class, 'savedPrograms']);
        Route::get('applications', [FundingApplicationController::class, 'index']);
        Route::post('applications', [FundingApplicationController::class, 'store']);
        Route::get('applications/{application}', [FundingApplicationController::class, 'show']);
        Route::post('applications/{application}/request-consultant', [FundingApplicationController::class, 'requestConsultant']);
    });
});
