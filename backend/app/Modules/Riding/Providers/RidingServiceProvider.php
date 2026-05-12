<?php

declare(strict_types=1);

namespace App\Modules\Riding\Providers;

use App\Modules\Riding\Services\DispatchService;
use App\Modules\Riding\Services\DriverCandidateResolver;
use App\Modules\Riding\Services\DriverOfferQueue;
use App\Modules\Riding\Services\RideStateMachine;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

final class RidingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(RideStateMachine::class);
        $this->app->singleton(DriverOfferQueue::class);
        $this->app->singleton(DriverCandidateResolver::class);
        $this->app->singleton(DispatchService::class);
    }

    public function boot(): void
    {
        Route::middleware('api')->group(__DIR__.'/../routes/api.php');
    }
}
