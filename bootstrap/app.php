<?php

use App\Exceptions\WebhookVerificationException;
use App\Http\Middleware\AdminAuth;
use App\Http\Middleware\LogMessengerWebhook;
use App\Http\Middleware\VerifyMetaSignature;
use App\Http\Middleware\WebhookRateLimit;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        channels: __DIR__ . '/../routes/channels.php',
        health: '/up',
        then: function () {
            Route::middleware('webhook')
                ->prefix('webhooks')
                ->name('webhooks.')
                ->group(base_path('routes/webhooks.php'));
        }
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->group('webhook', [
            // Pass-through for WhatsApp / Instagram — only logs when channel = messenger.
            // Placed BEFORE signature check so we capture rejected requests too.
            LogMessengerWebhook::class,
            VerifyMetaSignature::class,
            WebhookRateLimit::class,
        ]);

        // BOG posts the payment callback server-to-server (no session/CSRF token).
        // Authenticity is enforced by Callback-Signature + receipt re-fetch instead.
        $middleware->validateCsrfTokens(except: [
            'payments/bog/callback',
        ]);

        $middleware->alias([
            'admin.auth' => AdminAuth::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->dontReport([
            WebhookVerificationException::class,
        ]);
    })
    ->create();
