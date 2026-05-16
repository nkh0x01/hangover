<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web:      __DIR__.'/../routes/web.php',
        api:      __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health:   '/up',
        then: function () {
            \Illuminate\Support\Facades\Route::middleware('webhook')
                ->prefix('webhooks')
                ->name('webhooks.')
                ->group(base_path('routes/webhooks.php'));
        }
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->group('webhook', [
            \App\Http\Middleware\VerifyMetaSignature::class,
            \App\Http\Middleware\WebhookRateLimit::class,
        ]);

        $middleware->alias([
            'admin.auth' => \App\Http\Middleware\AdminAuth::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->dontReport([
            \App\Exceptions\WebhookVerificationException::class,
        ]);
    })
    ->create();
