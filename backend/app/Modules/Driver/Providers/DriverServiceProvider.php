<?php

declare(strict_types=1);

namespace App\Modules\Driver\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

final class DriverServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // No singletons yet — Phase 1 part 2 adds DriverApprovalService.
    }

    public function boot(): void
    {
        if (file_exists($routes = __DIR__.'/../routes/api.php')) {
            $this->loadRoutesFromRoot($routes);
        }
    }

    private function loadRoutesFromRoot(string $file): void
    {
        Route::middleware('api')->group($file);
    }
}
