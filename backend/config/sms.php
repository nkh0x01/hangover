<?php

declare(strict_types=1);

return [
    'driver' => env('SMS_DRIVER', 'null'),

    'from' => env('SMS_FROM', 'Hangover'),

    'drivers' => [
        'null' => [
            'class' => App\Modules\Communication\Sms\NullSmsGateway::class,
        ],
        'twilio' => [
            'class' => App\Modules\Communication\Sms\TwilioSmsGateway::class,
            'sid' => env('TWILIO_ACCOUNT_SID'),
            'token' => env('TWILIO_AUTH_TOKEN'),
            'from' => env('TWILIO_FROM'),
        ],
    ],

    'otp' => [
        'length' => 6,
        'ttl_minutes' => 5,
        'max_attempts' => 5,
        'resend_cooldown_seconds' => 60,
    ],
];
