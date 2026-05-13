<?php

declare(strict_types=1);

use App\Http\Middleware\EnforceAppVersion;
use App\Http\Middleware\EnsureDeviceBound;
use App\Http\Middleware\EnsureIdempotency;
use App\Http\Middleware\EnsureNotBlocked;
use App\Http\Middleware\LocalizeRequest;
use App\Http\Middleware\LogRequestId;
use App\Support\Exceptions\DomainException;
use App\Support\Http\JsonErrorRenderer;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Http\Middleware\CheckAbilities;
use Laravel\Sanctum\Http\Middleware\CheckForAnyAbility;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        // health endpoint is defined explicitly in routes/api.php so the
        // JSON envelope matches App\Support\Http\JsonErrorRenderer.
        apiPrefix: 'api',
        then: function (): void {
            // Module API route files are loaded via App\Providers\ModuleServiceProvider
            // so that each module owns its own routing surface.
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->use([
            LogRequestId::class,
        ]);

        $middleware->api(prepend: [
            LocalizeRequest::class,
        ]);

        $middleware->alias([
            'device.bound' => EnsureDeviceBound::class,
            'app.version' => EnforceAppVersion::class,
            'idempotent' => EnsureIdempotency::class,
            'not_blocked' => EnsureNotBlocked::class,
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'ability' => CheckForAnyAbility::class,
            'abilities' => CheckAbilities::class,
        ]);

        // Sanctum stateful domains for SPA / admin panel sessions.
        $middleware->statefulApi();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (Throwable $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return match (true) {
                $e instanceof ValidationException => JsonErrorRenderer::validation($e),
                $e instanceof AuthenticationException => JsonErrorRenderer::unauthenticated($e),
                $e instanceof AuthorizationException => JsonErrorRenderer::forbidden($e),
                $e instanceof DomainException => JsonErrorRenderer::domain($e),
                $e instanceof HttpExceptionInterface => JsonErrorRenderer::http($e),
                default => JsonErrorRenderer::unexpected($e),
            };
        });

        // Forward non-domain exceptions to Sentry when the package is
        // installed and a DSN is configured. The integration is opt-in
        // via env so tests and local dev never report.
        $exceptions->reportable(function (Throwable $e): bool {
            if (! function_exists('\Sentry\captureException')) {
                return true; // let the default reporter run
            }
            if ($e instanceof ValidationException
                || $e instanceof AuthenticationException
                || $e instanceof AuthorizationException
                || $e instanceof DomainException) {
                // These are expected client errors, not ops alerts.
                return true;
            }
            \Sentry\captureException($e);

            return true;
        });
    })
    ->create();
