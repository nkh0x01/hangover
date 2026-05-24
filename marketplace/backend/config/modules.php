<?php

declare(strict_types=1);

/*
 * Marketplace domain modules. Loaded by App\Providers\ModulesServiceProvider
 * in the order defined here. Each module is a class extending Illuminate\Support\ServiceProvider
 * and lives at App\Modules\<Name>\Providers\<Name>ServiceProvider.
 */

return [
    'modules' => [
        \App\Modules\Identity\Providers\IdentityServiceProvider::class,
        \App\Modules\Catalog\Providers\CatalogServiceProvider::class,
        \App\Modules\Seller\Providers\SellerServiceProvider::class,
        \App\Modules\Commerce\Providers\CommerceServiceProvider::class,
        \App\Modules\Review\Providers\ReviewServiceProvider::class,
        \App\Modules\Financing\Providers\FinancingServiceProvider::class,
        \App\Modules\Notification\Providers\NotificationServiceProvider::class,
        \App\Modules\Cms\Providers\CmsServiceProvider::class,
        \App\Modules\Admin\Providers\AdminServiceProvider::class,
    ],
];
