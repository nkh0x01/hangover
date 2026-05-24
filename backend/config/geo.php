<?php

declare(strict_types=1);
use App\Modules\Geo\Providers\Maps\GoogleMapsProvider;
use App\Modules\Geo\Providers\Maps\MapboxProvider;

return [
    'provider' => env('GEO_PROVIDER', 'google'),

    'providers' => [
        'google' => [
            'class' => GoogleMapsProvider::class,
            'key' => env('GOOGLE_MAPS_SERVER_KEY'),
        ],
        'mapbox' => [
            'class' => MapboxProvider::class,
            'token' => env('MAPBOX_ACCESS_TOKEN'),
        ],
    ],

    /**
     * Hot driver index — Redis connection used by NearbyDriverIndex.
     */
    'index' => [
        'enabled' => env('GEO_INDEX_ENABLED', true),
        'connection' => 'geo',
        'set_prefix' => 'drivers:online',
        'driver_meta_prefix' => 'driver',
        'meta_ttl_seconds' => 60,
    ],

    /**
     * Plausibility filters for inbound driver heartbeats.
     */
    'plausibility' => [
        'max_speed_kmh' => 80.0,
        'min_accuracy_m' => 60.0,
    ],
];
