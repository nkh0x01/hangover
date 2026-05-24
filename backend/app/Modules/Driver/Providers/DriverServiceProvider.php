<?php

declare(strict_types=1);

namespace App\Modules\Driver\Providers;

use App\Modules\Driver\Actions\ReviewDriverDocument;
use App\Modules\Driver\Actions\SubmitDriverDocument;
use App\Modules\Driver\Actions\VerifyVehicle;
use App\Modules\Driver\Services\DriverProfileSummary;
use App\Modules\Driver\Services\DriverVerificationPresenter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

final class DriverServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SubmitDriverDocument::class);
        $this->app->singleton(ReviewDriverDocument::class);
        $this->app->singleton(VerifyVehicle::class);
        $this->app->singleton(DriverVerificationPresenter::class);
        $this->app->singleton(DriverProfileSummary::class);
    }

    public function boot(): void
    {
        if (file_exists($routes = __DIR__.'/../routes/api.php')) {
            $this->loadRoutesFromRoot($routes);
        }
    }

    private function loadRoutesFromRoot(string $file): void
    {
        Route::prefix('api')->middleware('api')->group($file);
    }
}
