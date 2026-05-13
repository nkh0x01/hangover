<?php

declare(strict_types=1);

use App\Modules\Support\Http\Controllers\ComplaintController;
use App\Modules\Support\Http\Controllers\SosController;
use Illuminate\Support\Facades\Route;

/*
 * Phase 2.4 — safety endpoints. All require authentication +
 * device binding. SOS is rate-limited at one request per 10 seconds
 * per user (the mobile clients pre-confirm before posting).
 *
 * Mounted under `/api/v1/safety/...` by the module service provider.
 */

Route::middleware(['auth:sanctum', 'device.bound', 'not_blocked'])
    ->prefix('v1/safety')
    ->group(function (): void {
        Route::post('sos', SosController::class)
            ->middleware('throttle:6,1')
            ->name('safety.sos');

        Route::post('complaints', ComplaintController::class)
            ->middleware('throttle:30,1')
            ->name('safety.complaints');
    });
