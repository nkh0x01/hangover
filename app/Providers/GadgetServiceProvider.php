<?php

namespace App\Providers;

use App\Services\Gadget\CatalogSync;
use App\Services\Gadget\CouponSync;
use App\Services\Gadget\CustomerLink;
use App\Services\Gadget\GadgetApi;
use App\Services\Gadget\Mappers\CouponMapper;
use App\Services\Gadget\Mappers\CustomerMapper;
use App\Services\Gadget\Mappers\OrderMapper;
use App\Services\Gadget\Mappers\ProductMapper;
use App\Services\Gadget\OrderPush;
use App\Services\Gadget\WooCommerceClient;
use Illuminate\Support\ServiceProvider;

class GadgetServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(WooCommerceClient::class);
        $this->app->singleton(GadgetApi::class);

        $this->app->singleton(ProductMapper::class);
        $this->app->singleton(CouponMapper::class);
        $this->app->singleton(CustomerMapper::class);
        $this->app->singleton(OrderMapper::class);

        $this->app->singleton(CatalogSync::class);
        $this->app->singleton(CouponSync::class);
        $this->app->singleton(CustomerLink::class);
        $this->app->singleton(OrderPush::class);
    }
}
