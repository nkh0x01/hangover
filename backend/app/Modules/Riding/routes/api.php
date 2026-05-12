<?php

declare(strict_types=1);

use App\Modules\Riding\Http\Controllers\Customer\RideController as CustomerRideController;
use App\Modules\Riding\Http\Controllers\Driver\OfferController as DriverOfferController;
use App\Modules\Riding\Http\Controllers\Driver\RideController as DriverRideController;
use Illuminate\Support\Facades\Route;

// ---------- customer surface -------------------------------------------------
Route::prefix('v1/customer')
    ->middleware(['auth:sanctum', 'ability:customer'])
    ->group(function (): void {
        Route::get('/rides/active', [CustomerRideController::class, 'active'])
            ->middleware('throttle:api.default')
            ->name('customer.rides.active');

        Route::post('/rides', [CustomerRideController::class, 'store'])
            ->middleware(['throttle:rides.create'])
            ->name('customer.rides.store');

        Route::get('/rides', [CustomerRideController::class, 'index'])
            ->middleware('throttle:api.default')
            ->name('customer.rides.index');

        Route::get('/rides/{ulid}', [CustomerRideController::class, 'show'])
            ->middleware('throttle:api.default')
            ->name('customer.rides.show');

        Route::patch('/rides/{ulid}/cancel', [CustomerRideController::class, 'cancel'])
            ->middleware('throttle:api.write')
            ->name('customer.rides.cancel');
    });

// ---------- driver surface ---------------------------------------------------
Route::prefix('v1/driver')
    ->middleware(['auth:sanctum', 'ability:driver'])
    ->group(function (): void {
        Route::get('/rides/active', [DriverRideController::class, 'active'])
            ->name('driver.rides.active');

        Route::get('/rides/{ulid}', [DriverRideController::class, 'show'])
            ->name('driver.rides.show');

        Route::post('/rides/{ulid}/arriving', [DriverRideController::class, 'arriving'])
            ->middleware('throttle:api.write')
            ->name('driver.rides.arriving');

        Route::post('/rides/{ulid}/arrived', [DriverRideController::class, 'arrived'])
            ->middleware('throttle:api.write')
            ->name('driver.rides.arrived');

        Route::post('/rides/{ulid}/start', [DriverRideController::class, 'start'])
            ->middleware('throttle:api.write')
            ->name('driver.rides.start');

        Route::post('/rides/{ulid}/complete', [DriverRideController::class, 'complete'])
            ->middleware('throttle:api.write')
            ->name('driver.rides.complete');

        Route::patch('/rides/{ulid}/cancel', [DriverRideController::class, 'cancel'])
            ->middleware('throttle:api.write')
            ->name('driver.rides.cancel');

        Route::post('/offers/{ulid}/accept', [DriverOfferController::class, 'accept'])
            ->middleware('throttle:api.write')
            ->name('driver.offers.accept');

        Route::post('/offers/{ulid}/reject', [DriverOfferController::class, 'reject'])
            ->middleware('throttle:api.write')
            ->name('driver.offers.reject');
    });
