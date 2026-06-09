<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // WooCommerceClient binding moved to GadgetServiceProvider so it isn't
        // overridden by its own `singleton(WooCommerceClient::class)` call.
    }

    public function boot(): void {}
}
