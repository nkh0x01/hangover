<?php

declare(strict_types=1);

/*
 * Mobile push-notification driver config.
 *
 * Available drivers:
 *   null     — discards every push; logs at debug. Used by tests + local dev.
 *   firebase — kreait/laravel-firebase. Requires FIREBASE_CREDENTIALS env
 *              pointing at a service-account JSON.
 */

return [
    'driver' => env('PUSH_DRIVER', 'null'),

    // Channel IDs registered in the mobile apps' flutter_local_notifications.
    'channels' => [
        'rides' => 'hangover_rides',
        'offers' => 'hangover_offers',
    ],

    // Apple sound resource name (bundled in the iOS app target).
    'offer_sound_ios' => 'offer.caf',

    // Soft TTL — if a ride-offer push has been queued for longer than
    // this, the worker should drop it (the offer has expired anyway).
    'offer_ttl_seconds' => 15,
];
