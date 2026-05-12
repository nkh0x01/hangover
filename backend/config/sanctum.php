<?php

declare(strict_types=1);

use Laravel\Sanctum\Sanctum;

return [
    'stateful' => explode(',', (string) env('SANCTUM_STATEFUL_DOMAINS', sprintf(
        '%s%s',
        'localhost,localhost:8000,127.0.0.1,127.0.0.1:8000,::1',
        Sanctum::currentApplicationUrlWithPort()
    ))),

    'guard' => ['web'],

    /**
     * Access token TTL in minutes — default 30 days. Mobile clients
     * refresh well before this; admin sessions use the web guard.
     */
    'expiration' => (int) env('SANCTUM_TOKEN_TTL_MINUTES', 60 * 24 * 30),

    /**
     * Refresh grace window: how long after expiration we still allow a
     * refresh exchange. Past this, the user must re-authenticate.
     */
    'refresh_grace_minutes' => (int) env('SANCTUM_REFRESH_GRACE_MINUTES', 60 * 24 * 7),

    'token_prefix' => env('SANCTUM_TOKEN_PREFIX', 'hng_'),

    'middleware' => [
        'authenticate_session' => Laravel\Sanctum\Http\Middleware\AuthenticateSession::class,
        'encrypt_cookies' => Illuminate\Cookie\Middleware\EncryptCookies::class,
        'validate_csrf_token' => Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
    ],
];
