<?php

declare(strict_types=1);

namespace App\Modules\Riding\Filament\Widgets;

use App\Modules\Driver\Models\Driver;
use App\Modules\Riding\Models\Ride;
use App\Modules\Riding\StateMachine\RideStatus;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

/**
 * Pilot dashboard — KPIs the ops team watches during the controlled
 * launch. Filters out `is_test_ride = true` so the numbers reflect
 * real-customer activity.
 *
 * Layout reads top-to-bottom: throughput → quality → supply.
 */
final class PilotMetricsWidget extends BaseWidget
{
    protected static ?int $sort = 0;

    protected int|string|array $columnSpan = 'full';

    protected function getStats(): array
    {
        $today = now()->startOfDay();
        $rides24h = Ride::query()
            ->where('is_test_ride', false)
            ->where('requested_at', '>=', $today);

        $completed = (clone $rides24h)->where('status', RideStatus::Completed)->count();
        $cancelled = (clone $rides24h)->where('status', RideStatus::Cancelled)->count();
        $noDrivers = (clone $rides24h)->where('status', RideStatus::NoDrivers)->count();
        $total = (clone $rides24h)->count();

        $cancelRate = $total > 0 ? round($cancelled / $total * 100, 1) : 0;
        $noDriversRate = $total > 0 ? round($noDrivers / $total * 100, 1) : 0;

        $avgPickupSeconds = (int) (clone $rides24h)
            ->where('status', RideStatus::Completed)
            ->whereNotNull('accepted_at')
            ->whereNotNull('arrived_at')
            ->avg(DB::raw('TIMESTAMPDIFF(SECOND, accepted_at, arrived_at)'));

        $onlineDrivers = Driver::query()->where('status', 'online')->count();
        $thresholdMin = (int) config('pilot.monitoring.min_active_drivers', 3);
        $maxCancelRate = (float) config('pilot.monitoring.max_cancellation_rate', 0.20);

        return [
            Stat::make('Rides today', $total)
                ->description('Real customers (test rides excluded)')
                ->color('primary'),
            Stat::make('Completed', $completed)
                ->color('success'),
            Stat::make('Cancel rate', $cancelRate.'%')
                ->description($cancelRate / 100 > $maxCancelRate ? 'Above threshold' : 'Within target')
                ->color($cancelRate / 100 > $maxCancelRate ? 'danger' : 'success'),
            Stat::make('No-drivers rate', $noDriversRate.'%')
                ->description($noDrivers === 0 ? 'No supply gaps today' : "{$noDrivers} requests went unmatched")
                ->color($noDrivers > 0 ? 'warning' : 'success'),
            Stat::make('Avg pickup time', $avgPickupSeconds > 0 ? gmdate('i:s', $avgPickupSeconds) : '—')
                ->description('Accepted → driver arrived'),
            Stat::make('Online drivers', $onlineDrivers)
                ->description($onlineDrivers < $thresholdMin ? "Below floor of {$thresholdMin}" : 'Supply OK')
                ->color($onlineDrivers < $thresholdMin ? 'danger' : 'success'),
        ];
    }
}
