<?php

declare(strict_types=1);

use App\Modules\Pricing\Http\Controllers\EstimateController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/customer')
    ->middleware(['auth:sanctum', 'ability:customer'])
    ->group(function (): void {
        Route::post('/rides/estimates', [EstimateController::class, 'store'])
            ->middleware('throttle:api.write')
            ->name('customer.rides.estimates');
    });
