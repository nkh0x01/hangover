<?php

declare(strict_types=1);

/*
 * Sentry config (sentry/sentry-laravel ^4.10).
 *
 * Driver is opt-in: leave SENTRY_LARAVEL_DSN empty to disable reporting
 * (the default in local / testing). When set, the bootstrap exception
 * handler forwards uncaught exceptions to Sentry.
 *
 * Run `php artisan sentry:publish --dsn=<DSN>` after composer install
 * to scaffold the project; the values below already match what that
 * command emits.
 */

return [
    'dsn' => env('SENTRY_LARAVEL_DSN'),

    'release' => env('SENTRY_RELEASE'),

    'environment' => env('APP_ENV', 'production'),

    'breadcrumbs' => [
        'logs' => true,
        'cache' => false,
        'livewire' => false,
        'sql_queries' => false,
        'sql_bindings' => false,
        'queue_info' => true,
        'command_info' => true,
        'http_client_requests' => true,
        'notifications' => false,
    ],

    'tracing' => [
        'enabled' => env('SENTRY_TRACES_ENABLED', false),
        'sample_rate' => (float) env('SENTRY_TRACES_SAMPLE_RATE', 0.05),
        'queue_jobs' => true,
        'queue_job_transactions' => true,
        'sql_queries' => false,
        'sql_origin' => false,
        'views' => false,
        'http_client_requests' => true,
        'redis_commands' => false,
        'missing_routes' => false,
    ],

    'send_default_pii' => false,

    // Strip phone numbers + Sanctum tokens from breadcrumb messages.
    'before_send' => [\App\Support\Observability\SentryScrub::class, 'beforeSend'],
    'before_send_transaction' => null,

    // Default + extra integrations
    'integrations' => [],
];
