<?php

declare(strict_types=1);

/*
 * Phase 2.3 — commission rules.
 *
 * The platform fee retained from every completed-ride fare. Pilot
 * defaults to 15% across the board; once we hit GA we'll segment by
 * city, vehicle class, and driver tier.
 *
 * Per-driver overrides live on `drivers.commission_rate_override`
 * (e.g. promotional 0% week for newly-onboarded drivers).
 *
 * Per-city overrides land here so finance can iterate without a
 * code change — Pricing module reads them.
 */

return [
    'default_rate' => (float) env('COMMISSION_DEFAULT_RATE', 0.15),

    // Minimum + maximum commission per ride, to guard against bad
    // fare inputs (e.g. rare rounding edge cases producing zero or
    // surge multiplier bugs producing huge fares). Values in major
    // currency units.
    'min_amount' => (float) env('COMMISSION_MIN_AMOUNT', 0.10),
    'max_amount' => (float) env('COMMISSION_MAX_AMOUNT', 50.00),

    // Per-city override. Keys are `City.slug` (e.g. 'tbilisi',
    // 'batumi'). The City model also has a `default_commission_rate`
    // column — config takes precedence over that, which takes
    // precedence over `default_rate`.
    'by_city' => [
        // 'tbilisi' => 0.15,
        // 'batumi' => 0.12,
    ],

    // Currency of the platform-internal ledger. Wallets are mono-
    // currency per region; cross-currency rides are out of scope for
    // pilot.
    'ledger_currency' => env('LEDGER_CURRENCY', 'GEL'),
];
