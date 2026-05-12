<?php

declare(strict_types=1);
use App\Modules\Payment\Gateways\StripeGateway;

return [
    'default' => env('PAYMENTS_DEFAULT_GATEWAY', 'stripe'),

    'gateways' => [
        'stripe' => [
            'class' => StripeGateway::class,
            'key' => env('STRIPE_KEY'),
            'secret' => env('STRIPE_SECRET'),
            'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
        ],
        // Local Georgian gateways arrive in Phase 6+. Empty stubs intentionally
        // commented out so the abstraction is visible.
        // 'bog' => ['class' => App\Modules\Payment\Gateways\BogGateway::class],
        // 'tbc_pay' => ['class' => App\Modules\Payment\Gateways\TbcPayGateway::class],
    ],

    'methods_enabled' => [
        'cash' => true,
        'card' => true,
        'wallet' => true,
        'apple_pay' => true,
        'google_pay' => true,
    ],
];
