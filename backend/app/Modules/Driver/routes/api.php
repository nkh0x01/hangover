<?php

declare(strict_types=1);

use App\Modules\Driver\Http\Controllers\HeartbeatController;
use App\Modules\Driver\Http\Controllers\StatusController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/driver')
    ->middleware(['auth:sanctum', 'ability:driver'])
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
    });
