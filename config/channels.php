<?php

return [
    'whatsapp' => [
        'driver'              => \App\Services\Channels\WhatsAppDriver::class,
        'verify_token'        => env('WHATSAPP_VERIFY_TOKEN'),
        'app_secret'          => env('WHATSAPP_APP_SECRET'),
        'phone_number_id'     => env('WHATSAPP_PHONE_NUMBER_ID'),
        'business_account_id' => env('WHATSAPP_BUSINESS_ACCOUNT_ID'),
        'access_token'        => env('WHATSAPP_ACCESS_TOKEN'),
        'graph_version'       => env('WHATSAPP_GRAPH_VERSION', 'v20.0'),
        'graph_base'          => 'https://graph.facebook.com',
    ],

    'messenger' => [
        'driver'              => \App\Services\Channels\MessengerDriver::class,
        'verify_token'        => env('MESSENGER_VERIFY_TOKEN'),
        'app_secret'          => env('MESSENGER_APP_SECRET'),
        'page_id'             => env('MESSENGER_PAGE_ID'),
        'page_access_token'   => env('MESSENGER_PAGE_ACCESS_TOKEN'),
        'graph_version'       => env('WHATSAPP_GRAPH_VERSION', 'v20.0'),
        'graph_base'          => 'https://graph.facebook.com',
    ],

    'instagram' => [
        'driver'              => \App\Services\Channels\InstagramDriver::class,
        'verify_token'        => env('INSTAGRAM_VERIFY_TOKEN'),
        'app_secret'          => env('INSTAGRAM_APP_SECRET'),
        'ig_account_id'       => env('INSTAGRAM_ACCOUNT_ID'),
        'access_token'        => env('INSTAGRAM_ACCESS_TOKEN'),
        'graph_version'       => env('WHATSAPP_GRAPH_VERSION', 'v20.0'),
        'graph_base'          => 'https://graph.facebook.com',
    ],

    'facebook' => [
        // Facebook page comments share the Messenger driver's auth but
        // a different reply endpoint.
        'driver'              => \App\Services\Channels\FacebookCommentsDriver::class,
        'page_id'             => env('FACEBOOK_PAGE_ID'),
        'page_access_token'   => env('FACEBOOK_PAGE_ACCESS_TOKEN'),
        'graph_version'       => env('WHATSAPP_GRAPH_VERSION', 'v20.0'),
        'graph_base'          => 'https://graph.facebook.com',
    ],
];
