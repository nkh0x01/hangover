<?php

declare(strict_types=1);

return [
    'api_path' => 'api/v1',
    'api_domain' => null,

    'info' => [
        'version' => env('APP_VERSION', '1.0.0'),
        'title' => 'Hangover Mobility API',
        'description' => 'REST API for the Hangover Mobility Platform — customer, driver, admin, and webhook surfaces.',
    ],

    'servers' => null,

    'middleware' => [
        'web',
    ],

    'ui' => [
        'path' => 'docs/api',
    ],

    'extensions' => [],
];
