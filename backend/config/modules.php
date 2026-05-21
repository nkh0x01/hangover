<?php

declare(strict_types=1);
use App\Modules\Cms\Providers\CmsServiceProvider;
use App\Modules\Communication\Providers\CommunicationServiceProvider;
use App\Modules\Driver\Providers\DriverServiceProvider;
use App\Modules\Geo\Providers\GeoServiceProvider;
use App\Modules\Identity\Providers\IdentityServiceProvider;
use App\Modules\Payment\Providers\PaymentServiceProvider;
use App\Modules\Pricing\Providers\PricingServiceProvider;
use App\Modules\Promotion\Providers\PromotionServiceProvider;
use App\Modules\Rating\Providers\RatingServiceProvider;
use App\Modules\Riding\Providers\RidingServiceProvider;
use App\Modules\Support\Providers\SupportServiceProvider;
use App\Modules\Wallet\Providers\WalletServiceProvider;

/**
 * Module registry. Order matters only insofar as dependency direction —
 * Identity must boot before any other module that wants to read users.
 */
return [
    'enabled' => [
        IdentityServiceProvider::class,
        GeoServiceProvider::class,
        DriverServiceProvider::class,
        PricingServiceProvider::class,
        RidingServiceProvider::class,
        PaymentServiceProvider::class,
        WalletServiceProvider::class,
        PromotionServiceProvider::class,
        RatingServiceProvider::class,
        CommunicationServiceProvider::class,
        SupportServiceProvider::class,
        CmsServiceProvider::class,
    ],
];
