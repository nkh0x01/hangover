<?php

declare(strict_types=1);

use App\Modules\Driver\Http\Controllers\ApplicationController;
use App\Modules\Driver\Http\Controllers\DocumentController;
use App\Modules\Driver\Http\Controllers\HeartbeatController;
use App\Modules\Driver\Http\Controllers\StatusController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/driver')
    ->middleware(['auth:sanctum', 'ability:driver,driver:onboarding', 'not_blocked'])
    ->group(function (): void {
        Route::get('/application', [ApplicationController::class, 'show'])
            ->name('driver.application.show');
        Route::post('/application', [ApplicationController::class, 'store'])
            ->middleware('throttle:api.write')
            ->name('driver.application.store');
        Route::put('/application', [ApplicationController::class, 'update'])
            ->middleware('throttle:api.write')
            ->name('driver.application.update');
        Route::post('/application/submit', [ApplicationController::class, 'submit'])
            ->middleware('throttle:api.write')
            ->name('driver.application.submit');
        Route::post('/application/documents', [ApplicationController::class, 'document'])
            ->middleware('throttle:10,1')
            ->name('driver.application.documents.store');
    });

Route::prefix('v1/driver')
    ->middleware(['auth:sanctum', 'ability:driver', 'not_blocked'])
    ->group(function (): void {
        Route::post('/status/online', [StatusController::class, 'online'])
            ->middleware('throttle:api.write')
            ->name('driver.status.online');

        Route::post('/status/offline', [StatusController::class, 'offline'])
            ->middleware('throttle:api.write')
            ->name('driver.status.offline');

        Route::post('/location', HeartbeatController::class)
            ->middleware('throttle:driver.location')
            ->name('driver.location');

        // Verification documents
        Route::get('/documents', [DocumentController::class, 'index'])
            ->name('driver.documents.index');
        Route::post('/documents', [DocumentController::class, 'store'])
            ->middleware('throttle:10,1')
            ->name('driver.documents.store');
    });
