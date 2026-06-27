<?php

declare(strict_types=1);

/*
 * Phase 2.2 — pilot operations.
 *
 * `test_phone_numbers` — every customer phone (E.164) listed here has
 * their rides automatically tagged `is_test_ride = true` so they're
 * filtered out of the daily KPI dashboard. Drivers are not tagged
 * (their earnings still flow normally — we want real money in the
 * driver wallet during pilot).
 *
 * `pilot_cohort` — current cohort label stamped on test rides. NULL
 * = production. Set per env so we can run e.g. tbilisi-w1 in staging
 * while batumi-w1 is running in prod.
 *
 * `min_active_drivers` — the daily monitoring job emits a critical
 * page if the count of online drivers in any pilot city falls below
 * this floor.
 */

return [
    'enabled' => (bool) env('PILOT_ENABLED', false),
    'cohort' => env('PILOT_COHORT'),

    'test_phone_numbers' => array_values(array_filter(
        array_map('trim', explode(',', (string) env('PILOT_TEST_PHONES', ''))),
    )),

    'monitoring' => [
        'min_active_drivers' => (int) env('PILOT_MIN_DRIVERS', 3),
        'max_no_drivers_per_hour' => (int) env('PILOT_MAX_NO_DRIVERS_PER_HOUR', 5),
        'max_cancellation_rate' => (float) env('PILOT_MAX_CANCEL_RATE', 0.20),
    ],

    // Quiet hours during which pilot ride requests are blocked (HH:mm,
    // local Asia/Tbilisi). Empty array = no restriction. We enforce
    // this only when PILOT_ENABLED=true so prod isn't affected.
    'service_hours' => [
        'open' => env('PILOT_SERVICE_OPEN', '07:00'),
        'close' => env('PILOT_SERVICE_CLOSE', '23:00'),
    ],
];
