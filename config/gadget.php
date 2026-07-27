<?php

return [
    /*
    |--------------------------------------------------------------------------
    | gadget.ge / WooCommerce REST API
    |--------------------------------------------------------------------------
    | gadget.ge runs on WordPress + WooCommerce. We talk to it over the
    | standard WC REST API (/wp-json/wc/v3) using consumer-key + secret
    | (HTTP Basic over HTTPS).
    */
    'base_url' => env('GADGET_WC_BASE_URL', 'https://gadget.ge'),
    'api_path' => env('GADGET_WC_API_PATH', '/wp-json/wc/v3'),
    'consumer_key' => env('GADGET_WC_CONSUMER_KEY'),
    'consumer_secret' => env('GADGET_WC_CONSUMER_SECRET'),
    'webhook_secret' => env('GADGET_WC_WEBHOOK_SECRET'),

    'timeout' => (int) env('GADGET_WC_TIMEOUT', 20),
    'retries' => (int) env('GADGET_WC_RETRIES', 3),
    'verify_tls' => (bool) env('GADGET_WC_VERIFY_TLS', true),

    /*
    |--------------------------------------------------------------------------
    | New gadget.ge catalog API (Laravel + Bearer token)
    |--------------------------------------------------------------------------
    | gadget.ge migrated off WooCommerce to a Laravel site. Products/stock
    | now come from this API. See App\Services\Gadget\CatalogApiClient.
    */
    'api' => [
        'url' => env('GADGET_API_URL'),          // e.g. https://gadget.ge/api/v1
        'token' => env('GADGET_API_TOKEN'),      // Bearer token
        'timeout' => (int) env('GADGET_API_TIMEOUT', 25),
        'page_size' => (int) env('GADGET_API_PAGE_SIZE', 100),
    ],

    /*
    |--------------------------------------------------------------------------
    | Sync cadence (minutes)
    |--------------------------------------------------------------------------
    */
    'sync' => [
        'products_minutes' => (int) env('GADGET_WC_SYNC_PRODUCTS_MIN', 15),
        'coupons_minutes' => (int) env('GADGET_WC_SYNC_COUPONS_MIN', 30),
        'page_size' => (int) env('GADGET_WC_PAGE_SIZE', 100),
    ],

    /*
    |--------------------------------------------------------------------------
    | Default order behaviour
    |--------------------------------------------------------------------------
    | These are sent on POST /orders. payment_method maps from the
    | customer's choice inside the chat (branch|card|cod).
    */
    'orders' => [
        'currency_fallback' => 'GEL',
        'source_meta_key' => 'created_via',
        'source_meta_value' => 'gadget_ai_chatbot',
        'payment_methods' => [
            'branch' => ['id' => 'cod',    'title' => 'ფილიალში გადახდა', 'set_paid' => false],
            'cod' => ['id' => 'cod',    'title' => 'კურიერთან გადახდა', 'set_paid' => false],
            'card' => ['id' => 'bog',    'title' => 'ბარათით გადახდა',  'set_paid' => false],
        ],
        'shipping' => [
            'pickup_method_id' => 'local_pickup',
            'pickup_title' => 'ფილიალში მიტანა',
            'courier_method_id' => 'flat_rate',
            'courier_title' => 'კურიერი',
            'courier_fee' => 10.0,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Product field mapping
    |--------------------------------------------------------------------------
    | Maps WooCommerce product fields to our local `products` table.
    | Tweak these without touching code if Gadget customises the schema.
    */
    'product_map' => [
        'source_id' => 'id',
        'sku' => 'sku',
        'name' => 'name',
        'url' => 'permalink',
        'description' => 'short_description',
        'price' => 'regular_price',
        'price_promo' => 'sale_price',
        'is_promo' => 'on_sale',
        'stock' => 'stock_quantity',
        'stock_status' => 'stock_status',           // "instock" / "outofstock"
        'manage_stock' => 'manage_stock',
        'images' => 'images',                 // [{src}]
        'categories' => 'categories',             // [{name}]
        'brand_taxonomy' => 'product_brand',      // common Woo brands plugin
        'brand_attribute' => 'pa_brand',           // fallback: Woo attribute slug
    ],

    /*
    |--------------------------------------------------------------------------
    | Branch / location mapping
    |--------------------------------------------------------------------------
    | WooCommerce doesn't natively model per-branch stock. If the store
    | uses a multi-location plugin (e.g. WPM Multi-Location, ATUM),
    | we read it from `meta_data` keyed below. Configure to taste.
    */
    'branches' => [
        // Map of branch display name → meta_data key in WC.
        'meta_keys' => [
            'Saburtalo' => 'stock_saburtalo',
            'Vake' => 'stock_vake',
            'Gldani' => 'stock_gldani',
        ],
    ],
];
