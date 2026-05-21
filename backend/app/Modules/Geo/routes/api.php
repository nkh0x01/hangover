<?php

declare(strict_types=1);

use App\Modules\Geo\Http\Controllers\NearbyDriversController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/customer')
    ->middleware(['auth:sanctum', 'ability:customer'])
    ->group(function (): void {
        Route::get('/drivers/nearby', NearbyDriversController::class)
            ->middleware('throttle:api.default')
            ->name('customer.drivers.nearby');
    });
