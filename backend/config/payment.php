<?php

declare(strict_types=1);

/*
 * Phase 2.3 — payment gateway routing.
 *
 * `default` is the gateway used when the customer's chosen payment
 * method is `cash` or `card` with no explicit `provider_id`. Pilot
 * defaults to cash; card lights up the moment BOG/TBC creds appear.
 *
 * `methods` maps high-level payment methods to a gateway. The
 * resolver looks up the gateway, instantiates the binding, and
 * dispatches authorize → capture.
 *
 * Per-provider config is intentionally minimal here — secrets come
 * from .env; structural config (timeouts, retries) lives in the
 * provider's own block.
 */

return [
    'default' => env('PAYMENT_DEFAULT', 'cash'),

    'currency' => env('PAYMENT_CURRENCY', 'GEL'),

    'methods' => [
        'cash' => 'cash',
        'card' => env('PAYMENT_CARD_GATEWAY', 'null'),
        'wallet' => 'wallet',
        'apple_pay' => env('PAYMENT_APPLE_PAY_GATEWAY', 'stripe'),
        'google_pay' => env('PAYMENT_GOOGLE_PAY_GATEWAY', 'stripe'),
    ],

    'gateways' => [
        'cash' => [
            'class' => App\Modules\Payment\Gateways\CashPaymentGateway::class,
        ],
        'null' => [
            'class' => App\Modules\Payment\Gateways\NullPaymentGateway::class,
        ],
        'wallet' => [
            'class' => App\Modules\Payment\Gateways\WalletPaymentGateway::class,
        ],
        'stripe' => [
            'class' => App\Modules\Payment\Gateways\StripePaymentGateway::class,
            'secret_key' => env('STRIPE_SECRET_KEY'),
            'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
            'api_version' => '2024-12-18.acacia',
            'timeout_seconds' => 10,
        ],
        'bog' => [
            'class' => App\Modules\Payment\Gateways\BogPaymentGateway::class,
            'base_url' => env('BOG_BASE_URL', 'https://api.bog.ge/payments/v1'),
            'client_id' => env('BOG_CLIENT_ID'),
            'client_secret' => env('BOG_CLIENT_SECRET'),
            'merchant_id' => env('BOG_MERCHANT_ID'),
            'timeout_seconds' => 10,
        ],
        'tbc_pay' => [
            'class' => App\Modules\Payment\Gateways\TbcPayPaymentGateway::class,
            'base_url' => env('TBC_PAY_BASE_URL', 'https://api.tbcpay.ge/v1'),
            'api_key' => env('TBC_PAY_API_KEY'),
            'api_secret' => env('TBC_PAY_API_SECRET'),
            'campaign_id' => env('TBC_PAY_CAMPAIGN_ID'),
            'timeout_seconds' => 10,
        ],
    ],

    // Retry policy for transient gateway failures.
    'retry' => [
        'attempts' => (int) env('PAYMENT_RETRY_ATTEMPTS', 3),
        'backoff_seconds' => [1, 5, 30],
    ],
];
