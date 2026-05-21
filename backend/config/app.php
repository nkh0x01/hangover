<?php

declare(strict_types=1);

return [
    'name' => env('APP_NAME', 'Hangover Mobility'),
    'env' => env('APP_ENV', 'production'),
    'debug' => (bool) env('APP_DEBUG', false),
    'url' => env('APP_URL', 'http://localhost'),
    'asset_url' => env('ASSET_URL'),

    'timezone' => env('APP_TIMEZONE', 'UTC'),
    'locale' => env('APP_LOCALE', 'ka'),
    'fallback_locale' => env('APP_FALLBACK_LOCALE', 'en'),
    'faker_locale' => env('APP_FAKER_LOCALE', 'ka_GE'),

    'release' => env('APP_RELEASE', 'dev'),

    'admin_email' => env('ADMIN_EMAIL', 'admin@hangover.local'),
    'admin_password' => env('ADMIN_PASSWORD', 'change-me-on-first-login'),

    'supported_locales' => ['ka', 'en', 'ru'],

    'cipher' => 'AES-256-CBC',
    'key' => env('APP_KEY'),
    'previous_keys' => array_filter(explode(',', (string) env('APP_PREVIOUS_KEYS', ''))),

    'maintenance' => [
        'driver' => env('APP_MAINTENANCE_DRIVER', 'file'),
        'store' => env('APP_MAINTENANCE_STORE', 'database'),
    ],

    'min_app_version' => [
        'ios' => env('APP_MIN_VERSION_IOS', '1.0.0'),
        'android' => env('APP_MIN_VERSION_ANDROID', '1.0.0'),
    ],
];
