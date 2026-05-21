<?php

declare(strict_types=1);

namespace App\Modules\Geo\Console;

use App\Modules\Driver\Models\Driver;
use App\Modules\Geo\Models\City;
use App\Modules\Geo\Services\NearbyDriverIndex;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;

/**
 * Belt-and-braces sweep: Redis already TTLs the meta hash per heartbeat,
 * but a driver whose process crashed mid-trip can leave its GEO entry
 * sitting until the next GEOADD on the same key bumps it. This command
 * walks the city's online drivers and removes any whose meta hash has
 * expired — which by construction means we have no recent heartbeat.
 *
 * Scheduled in app/Modules/Geo/Providers/GeoServiceProvider when wired
 * (Phase 2). For now operators can invoke manually:
 *
 *     php artisan geo:prune-stale --city=1
 */
final class PruneStaleDriversCommand extends Command
{
    protected $signature = 'geo:prune-stale {--city=}';

    protected $description = 'Remove drivers from the online geo index whose heartbeat TTL has expired.';

    public function handle(NearbyDriverIndex $index): int
    {
        $cityIds = $this->option('city') !== null
            ? [(int) $this->option('city')]
            : City::query()->where('is_active', true)->pluck('id')->all();

        $totalPruned = 0;

        $conn = Redis::connection((string) config('geo.index.connection', 'geo'));

        foreach ($cityIds as $cityId) {
            $key = sprintf('%s:%d', (string) config('geo.index.set_prefix', 'drivers:online'), $cityId);
            /** @var array<int, string> $members */
            $members = (array) $conn->zrange($key, 0, -1);

            foreach ($members as $member) {
                if (! str_starts_with($member, 'driver:')) {
                    continue;
                }
                $driverId = (int) substr($member, strlen('driver:'));
                $metaKey = sprintf('%s:%d:meta', (string) config('geo.index.driver_meta_prefix', 'driver'), $driverId);

                if ((int) $conn->exists($metaKey) === 0) {
                    $index->remove($cityId, $driverId);
                    Driver::query()->whereKey($driverId)->update(['online' => false]);
                    $totalPruned++;
                    $this->line("pruned driver={$driverId} city={$cityId}");
                }
            }
        }

        $this->info("pruned {$totalPruned} stale driver(s)");

        return self::SUCCESS;
    }
}
