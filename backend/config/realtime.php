<?php

declare(strict_types=1);

return [
    'channels' => [
        'ride_prefix' => 'private-ride',
        'driver_prefix' => 'private-driver',
        'customer_prefix' => 'private-customer',
        'city_drivers_prefix' => 'presence-city.{cityId}.drivers',
        'city_rides_prefix' => 'presence-city.{cityId}.rides',
        'support_ticket_prefix' => 'private-support-ticket',
    ],

    'driver_location' => [
        'throttle_per_second' => 1,
        'idle_cadence_seconds' => 15,
        'active_cadence_seconds' => 2,
        'in_trip_cadence_seconds' => 1,
    ],

    'offer' => [
        'expiry_seconds' => 12,
        'max_radius_km' => 8,
        'initial_radius_km' => 3,
        'search_timeout_seconds' => 60,
    ],
];
