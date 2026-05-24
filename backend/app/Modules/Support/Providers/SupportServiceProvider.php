<?php

declare(strict_types=1);

namespace App\Modules\Support\Providers;

use App\Modules\Support\Actions\RaiseFraudFlag;
use App\Modules\Support\Actions\RaiseSosEvent;
use App\Modules\Support\Actions\SubmitComplaint;
use App\Modules\Support\Actions\SuspendUser;
use App\Modules\Support\Services\FraudDetector;
use App\Modules\Support\Services\IncidentTimelineService;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

final class SupportServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(RaiseSosEvent::class);
        $this->app->singleton(SubmitComplaint::class);
        $this->app->singleton(RaiseFraudFlag::class);
        $this->app->singleton(SuspendUser::class);
        $this->app->singleton(FraudDetector::class);
        $this->app->singleton(IncidentTimelineService::class);
    }

    public function boot(): void
    {
        Route::prefix('api')->middleware('api')->group(__DIR__.'/../routes/api.php');
    }
}
