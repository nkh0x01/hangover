<?php

declare(strict_types=1);

namespace App\Modules\Geo\Providers;

use App\Modules\Geo\Console\PruneStaleDriversCommand;
use App\Modules\Geo\Contracts\MapProvider;
use App\Modules\Geo\Services\NearbyDriverIndex;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use RuntimeException;

final class GeoServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(NearbyDriverIndex::class);

        $this->app->singleton(MapProvider::class, function (): MapProvider {
            $driver = (string) config('geo.provider', 'google');
            $cfg = config("geo.providers.$driver");

            if (! is_array($cfg) || ! isset($cfg['class']) || ! class_exists($cfg['class'])) {
                // Phase 0 ships only the interface — concrete providers in Phase 2.
                throw new RuntimeException("Map provider [{$driver}] not configured.");
            }

            /** @var class-string<MapProvider> $cls */
            $cls = $cfg['class'];

            return new $cls(...array_values(array_diff_key($cfg, ['class' => null])));
        });
    }

    public function boot(): void
    {
        Route::middleware('api')->group(__DIR__.'/../routes/api.php');

        if ($this->app->runningInConsole()) {
            $this->commands([
                PruneStaleDriversCommand::class,
            ]);
        }
    }
}
