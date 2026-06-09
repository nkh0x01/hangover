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
use App\Services\SettingsService;
use Illuminate\Support\ServiceProvider;

class GadgetServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // WooCommerceClient: overlay admin-saved DB credentials on top of
        // config('gadget'). DB values from SettingsService take precedence
        // so admins can rotate WC keys from the UI without a redeploy.
        $this->app->singleton(WooCommerceClient::class, function ($app) {
            $settings = $app->make(SettingsService::class);
            $config = (array) config('gadget');
            $overlay = [
                'GADGET_WC_BASE_URL' => 'base_url',
                'GADGET_WC_API_PATH' => 'api_path',
                'GADGET_WC_CONSUMER_KEY' => 'consumer_key',
                'GADGET_WC_CONSUMER_SECRET' => 'consumer_secret',
                'GADGET_WC_WEBHOOK_SECRET' => 'webhook_secret',
            ];
            foreach ($overlay as $settingKey => $configKey) {
                $val = $settings->get($settingKey);
                if ($val !== null && $val !== '') {
                    $config[$configKey] = $val;
                }
            }
            return new WooCommerceClient($config);
        });

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
