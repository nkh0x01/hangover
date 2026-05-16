<?php

return [
    'provider' => env('PAYMENT_PROVIDER', 'stub'),  // bog|tbc|stub

    'api_key'    => env('PAYMENT_API_KEY'),
    'api_secret' => env('PAYMENT_API_SECRET'),

    'return_url'   => env('PAYMENT_RETURN_URL'),
    'fail_url'     => env('PAYMENT_FAIL_URL'),
    'callback_url' => env('PAYMENT_CALLBACK_URL'),

    'bog' => [
        'oauth_url'  => 'https://oauth2.bog.ge/auth/realms/bog/protocol/openid-connect/token',
        'orders_url' => 'https://api.bog.ge/payments/v1/ecommerce/orders',
    ],
];
