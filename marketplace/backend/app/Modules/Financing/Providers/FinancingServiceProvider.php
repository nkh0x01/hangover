<?php

declare(strict_types=1);

namespace App\Modules\Financing\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class FinancingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $apiRoutes = __DIR__.'/../routes/api.php';
        if (file_exists($apiRoutes)) {
            Route::middleware('api')
                ->prefix('api/v1')
                ->group($apiRoutes);
        }

        $webRoutes = __DIR__.'/../routes/web.php';
        if (file_exists($webRoutes)) {
            Route::middleware('web')
                ->group($webRoutes);
        }
    }
}
