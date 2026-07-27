<?php

return [
    'provider' => env('PAYMENT_PROVIDER', 'stub'),  // bog|tbc|stub

    'api_key' => env('PAYMENT_API_KEY'),
    'api_secret' => env('PAYMENT_API_SECRET'),

    'return_url' => env('PAYMENT_RETURN_URL'),
    'fail_url' => env('PAYMENT_FAIL_URL'),
    'callback_url' => env('PAYMENT_CALLBACK_URL'),

    'bog' => [
        'oauth_url' => 'https://oauth2.bog.ge/auth/realms/bog/protocol/openid-connect/token',
        'orders_url' => 'https://api.bog.ge/payments/v1/ecommerce/orders',
        // Authoritative payment-details/receipt endpoint; append {order_id}.
        'receipt_url' => 'https://api.bog.ge/payments/v1/receipt/',
        // BOG's fixed public key for Callback-Signature (RSA-SHA256) verification.
        // Override via BOG_PUBLIC_KEY in .env if BOG ever rotates the key.
        'public_key' => env('BOG_PUBLIC_KEY', "-----BEGIN PUBLIC KEY-----\nMIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAu4RUyAw3+CdkS3ZNILQhzHI9Hemo+vKB9U2BSabppkKjzjjkf+0Sm76hSMiu/HFtYhqWOESryoCDJoqffY0Q1VNt25aTxbj068QNUtnxQ7KQVLA+pG0smf+EBWlS1vBEAFbIas9d8c9b9sSEkTrrTYQ90WIM8bGB6S/KLVoT1a7SnzabjoLc5Qf/SLDG5fu8dH8zckyeYKdRKSBJKvhxtcBuHV4f7qsynQT+f2UYbESX/TLHwT5qFWZDHZ0YUOUIvb8n7JujVSGZO9/+ll/g4ZIWhC1MlJgPObDwRkRd8NFOopgxMcMsDIZIoLbWKhHVq67hdbwpAq9K9WMmEhPnPwIDAQAB\n-----END PUBLIC KEY-----\n"),
    ],
];
