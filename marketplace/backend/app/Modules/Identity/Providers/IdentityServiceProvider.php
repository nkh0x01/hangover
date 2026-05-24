<?php

declare(strict_types=1);

namespace App\Modules\Identity\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class IdentityServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../../../../database/migrations');

        Route::middleware('api')
            ->prefix('api/v1')
            ->group(__DIR__.'/../routes/api.php');

        Route::middleware('web')
            ->group(__DIR__.'/../routes/web.php');
    }
}
