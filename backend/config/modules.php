<?php

declare(strict_types=1);

/**
 * Module registry. Order matters only insofar as dependency direction —
 * Identity must boot before any other module that wants to read users.
 */
return [
    'enabled' => [
        App\Modules\Identity\Providers\IdentityServiceProvider::class,
        App\Modules\Geo\Providers\GeoServiceProvider::class,
        App\Modules\Driver\Providers\DriverServiceProvider::class,
        App\Modules\Pricing\Providers\PricingServiceProvider::class,
        App\Modules\Riding\Providers\RidingServiceProvider::class,
        App\Modules\Payment\Providers\PaymentServiceProvider::class,
        App\Modules\Wallet\Providers\WalletServiceProvider::class,
        App\Modules\Promotion\Providers\PromotionServiceProvider::class,
        App\Modules\Rating\Providers\RatingServiceProvider::class,
        App\Modules\Communication\Providers\CommunicationServiceProvider::class,
        App\Modules\Support\Providers\SupportServiceProvider::class,
        App\Modules\Cms\Providers\CmsServiceProvider::class,
    ],
];
