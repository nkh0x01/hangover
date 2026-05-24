<?php

declare(strict_types=1);

namespace App\Modules\Identity\Providers;

use App\Modules\Identity\Services\OtpService;
use App\Modules\Identity\Services\TokenIssuer;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

final class IdentityServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // The Communication module owns the SmsGateway binding — but we
        // make sure CommunicationServiceProvider is registered before
        // OtpService is resolved by listing Communication after Identity
        // in config/modules.php and resolving lazily.
        $this->app->singleton(OtpService::class);
        $this->app->singleton(TokenIssuer::class);
    }

    public function boot(): void
    {
        Route::prefix('api')->middleware('api')->group(__DIR__.'/../routes/api.php');
    }
}
