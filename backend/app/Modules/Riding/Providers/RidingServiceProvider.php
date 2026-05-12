<?php

declare(strict_types=1);

namespace App\Modules\Riding\Providers;

use App\Modules\Riding\Services\RideStateMachine;
use Illuminate\Support\ServiceProvider;

final class RidingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(RideStateMachine::class);
    }

    public function boot(): void
    {
        // Phase 3 wires the customer/driver REST surfaces here.
    }
}
