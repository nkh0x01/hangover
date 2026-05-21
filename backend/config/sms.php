<?php

declare(strict_types=1);
use App\Modules\Communication\Sms\NullSmsGateway;
use App\Modules\Communication\Sms\SenderGeSmsGateway;
use App\Modules\Communication\Sms\TwilioSmsGateway;

return [
    'driver' => env('SMS_DRIVER', 'null'),

    'from' => env('SMS_FROM', 'Hangover'),

    'drivers' => [
        'null' => [
            'class' => NullSmsGateway::class,
        ],
        'twilio' => [
            'class' => TwilioSmsGateway::class,
            'sid' => env('TWILIO_ACCOUNT_SID'),
            'token' => env('TWILIO_AUTH_TOKEN'),
            'from' => env('TWILIO_FROM'),
        ],
        'sender_ge' => [
            'class' => SenderGeSmsGateway::class,
            'api_key' => env('SENDER_GE_API_KEY'),
            'sender' => env('SENDER_GE_SENDER', 'Ride360'),
            'base_url' => env('SENDER_GE_BASE_URL', 'https://sender.ge/api/send.php'),
        ],
    ],

    'otp' => [
        'length' => 6,
        'ttl_minutes' => 5,
        'max_attempts' => 5,
        'resend_cooldown_seconds' => 60,
        'per_phone_per_hour' => (int) env('RATELIMIT_OTP_PER_PHONE_PER_HOUR', 5),
    ],
];
