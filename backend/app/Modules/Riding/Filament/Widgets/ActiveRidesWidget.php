<?php

declare(strict_types=1);

namespace App\Modules\Riding\Filament\Widgets;

use App\Modules\Riding\Models\Ride;
use App\Modules\Riding\StateMachine\RideStatus;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

final class ActiveRidesWidget extends BaseWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $active = Ride::query()
            ->whereIn('status', [
                RideStatus::Requested->value,
                RideStatus::Searching->value,
                RideStatus::Offered->value,
                RideStatus::Accepted->value,
                RideStatus::DriverArriving->value,
                RideStatus::DriverArrived->value,
                RideStatus::InProgress->value,
            ])
            ->count();

        $completed24h = Ride::query()
            ->where('status', RideStatus::Completed)
            ->where('completed_at', '>=', now()->subDay())
            ->count();

        $cancelled24h = Ride::query()
            ->where('status', RideStatus::Cancelled)
            ->where('cancelled_at', '>=', now()->subDay())
            ->count();

        return [
            Stat::make('Active rides', $active)
                ->description('Currently in flight')
                ->color('warning'),
            Stat::make('Completed (24h)', $completed24h)
                ->color('success'),
            Stat::make('Cancelled (24h)', $cancelled24h)
                ->color('danger'),
        ];
    }
}
