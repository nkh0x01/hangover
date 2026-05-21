<?php

declare(strict_types=1);

return [
    'currency_default' => env('PRICING_CURRENCY_DEFAULT', 'GEL'),

    'fare_estimate_ttl_minutes' => env('PRICING_ESTIMATE_TTL_MINUTES', 30),

    /**
     * Multipliers / discount caps to bound any single fare calculation.
     */
    'caps' => [
        'min_fare' => 1.0,
        'max_fare' => 500.0,
        'max_surge_multiplier' => 5.0,
    ],
];
