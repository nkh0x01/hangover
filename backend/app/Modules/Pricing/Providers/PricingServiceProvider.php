<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Providers;

use App\Modules\Pricing\Services\FareEstimateService;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

final class PricingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(FareEstimateService::class);
    }

    public function boot(): void
    {
        Route::middleware('api')->group(__DIR__.'/../routes/api.php');
    }
}
