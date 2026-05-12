<?php

declare(strict_types=1);

namespace App\Modules\Driver\Filament\Widgets;

use App\Modules\Driver\Models\Driver;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

final class OnlineDriversWidget extends BaseWidget
{
    protected static ?int $sort = 2;

    protected function getStats(): array
    {
        $online = Driver::query()->where('online', true)->where('status', 'approved')->count();
        $pending = Driver::query()->where('status', 'pending')->count();
        $approved = Driver::query()->where('status', 'approved')->count();

        return [
            Stat::make('Online drivers', $online)->color('success'),
            Stat::make('Pending approval', $pending)->color('warning'),
            Stat::make('Approved (total)', $approved)->color('primary'),
        ];
    }
}
