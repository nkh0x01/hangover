<?php

declare(strict_types=1);

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Top-level API entry point
|--------------------------------------------------------------------------
|
| Each module wires its own /api/v1/* groups via routes loaded from its
| service provider. This file only owns the version umbrella and a few
| genuinely cross-cutting endpoints (health, version, config).
|
*/

Route::prefix('v1')->group(function (): void {

    // Health is also wired via withRouting(health: ...) but we keep an
    // explicit endpoint here so /api/v1/health is canonical.
    Route::get('/health', function (): JsonResponse {
        return new JsonResponse([
            'data' => [
                'status' => 'ok',
                'service' => config('app.name'),
                'time' => now()->toIso8601String(),
            ],
        ]);
    })->name('api.health');

    Route::get('/version', function (): JsonResponse {
        return new JsonResponse([
            'data' => [
                'min_app_version' => config('app.min_app_version'),
                'release' => env('APP_RELEASE', 'dev'),
            ],
        ]);
    })->name('api.version');

    Route::get('/config', function (): JsonResponse {
        return new JsonResponse([
            'data' => [
                'supported_locales' => config('app.supported_locales'),
                'map_provider' => config('geo.provider'),
                'payment_methods' => array_keys(array_filter((array) config('payments.methods_enabled'))),
                'reverb' => [
                    'host' => env('REVERB_HOST_CLIENT'),
                    'port' => (int) env('REVERB_PORT_CLIENT', 8080),
                    'scheme' => env('REVERB_SCHEME_CLIENT', 'ws'),
                    'key' => env('REVERB_APP_KEY'),
                ],
            ],
        ]);
    })->name('api.config');

});
