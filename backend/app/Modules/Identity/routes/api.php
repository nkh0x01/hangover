<?php

declare(strict_types=1);

use App\Modules\Identity\Http\Controllers\Auth\OtpController;
use App\Modules\Identity\Http\Controllers\Auth\SessionController;
use App\Modules\Identity\Http\Controllers\Profile\DeviceController;
use App\Modules\Identity\Http\Controllers\Profile\MeController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {

    // -- Public (unauthenticated) auth ---------------------------------
    Route::prefix('auth')->group(function (): void {
        Route::post('/otp/request', [OtpController::class, 'request'])
            ->middleware('throttle:auth.otp')
            ->name('auth.otp.request');

        Route::post('/otp/verify', [OtpController::class, 'verify'])
            ->middleware('throttle:auth.verify')
            ->name('auth.otp.verify');

        // OAuth controllers ship in Phase 1 part 2.
    });

    // -- Authenticated session / profile -------------------------------
    Route::middleware(['auth:sanctum'])->group(function (): void {
        Route::get('/auth/me', [MeController::class, 'show'])
            ->name('auth.me.show');

        Route::post('/auth/refresh', [SessionController::class, 'refresh'])
            ->middleware('throttle:auth.refresh')
            ->name('auth.refresh');

        Route::post('/auth/logout', [SessionController::class, 'logout'])
            ->name('auth.logout');

        // FCM/APNs token registration — same endpoint for both apps
        // since it acts on the bearer-token's device.
        Route::post('/me/devices/fcm-token', [DeviceController::class, 'registerFcmToken'])
            ->middleware('throttle:api.write')
            ->name('me.devices.fcm-token');

        // Customer profile endpoints
        Route::prefix('customer')->middleware('ability:customer')->group(function (): void {
            Route::get('/me', [MeController::class, 'show'])->name('customer.me.show');
            Route::patch('/me', [MeController::class, 'update'])->name('customer.me.update');
        });

        // Driver profile endpoints (approved or onboarding)
        Route::prefix('driver')->middleware('ability:driver,driver:onboarding')->group(function (): void {
            Route::get('/me', [MeController::class, 'show'])->name('driver.me.show');
            Route::patch('/me', [MeController::class, 'update'])->name('driver.me.update');
        });
    });
});
