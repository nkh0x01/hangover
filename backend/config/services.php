<?php

declare(strict_types=1);

return [
    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    // -- OAuth (Socialite) -------------------------------------------------
    'google' => [
        // The customer + driver apps use different OAuth client IDs but
        // share the server secret since auth happens via ID token verify.
        'client_id_customer' => env('GOOGLE_CLIENT_ID_CUSTOMER'),
        'client_id_driver'   => env('GOOGLE_CLIENT_ID_DRIVER'),
    ],

    'apple' => [
        'client_id_customer' => env('APPLE_CLIENT_ID_CUSTOMER'),
        'client_id_driver'   => env('APPLE_CLIENT_ID_DRIVER'),
        'team_id'            => env('APPLE_TEAM_ID'),
        'key_id'             => env('APPLE_KEY_ID'),
        'private_key_path'   => env('APPLE_PRIVATE_KEY_PATH'),
    ],

    // -- SMS providers (used by Identity\Services\SmsService) -------------
    'twilio' => [
        'sid'   => env('TWILIO_ACCOUNT_SID'),
        'token' => env('TWILIO_AUTH_TOKEN'),
        'from'  => env('TWILIO_FROM'),
    ],
];
