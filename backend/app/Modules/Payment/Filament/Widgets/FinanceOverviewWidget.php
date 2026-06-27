<?php

declare(strict_types=1);

namespace App\Modules\Payment\Filament\Widgets;

use App\Modules\Payment\Models\Payment;
use App\Modules\Payment\Models\Refund;
use App\Modules\Riding\Models\Ride;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Finance KPIs — gross revenue, commission take, refund leakage,
 * unsettled payments. Filters out test rides so the numbers
 * reflect production money flow.
 */
final class FinanceOverviewWidget extends BaseWidget
{
    protected static ?int $sort = 0;

    protected int|string|array $columnSpan = 'full';

    protected function getStats(): array
    {
        $today = now()->startOfDay();

        $todayRides = Ride::query()
            ->where('is_test_ride', false)
            ->where('completed_at', '>=', $today)
            ->whereNotNull('completed_at');

        $grossToday = (float) (clone $todayRides)->sum('final_amount');
        $commissionToday = (float) (clone $todayRides)->sum('commission_amount');
        $driverEarningsToday = (float) (clone $todayRides)->sum('driver_earnings');

        $refundsToday = (float) Refund::query()
            ->where('status', 'succeeded')
            ->where('created_at', '>=', $today)
            ->sum('amount');

        $unsettled = Payment::query()
            ->where('status', 'failed')
            ->where('created_at', '>=', now()->subDays(7))
            ->count();

        $cash24h = (float) Payment::query()
            ->where('method', 'cash')
            ->where('status', 'captured')
            ->where('captured_at', '>=', now()->subDay())
            ->sum('amount');

        return [
            Stat::make('Gross fares today', number_format($grossToday, 2).' GEL')
                ->description('Sum of final_amount over completed rides (excl. test)')
                ->color('primary'),
            Stat::make('Commission today', number_format($commissionToday, 2).' GEL')
                ->description($grossToday > 0 ? round($commissionToday / $grossToday * 100, 1).'% take' : '—')
                ->color('success'),
            Stat::make('Driver earnings today', number_format($driverEarningsToday, 2).' GEL')
                ->color('primary'),
            Stat::make('Refunds today', number_format($refundsToday, 2).' GEL')
                ->description($grossToday > 0 ? round($refundsToday / $grossToday * 100, 1).'% of gross' : '0%')
                ->color($refundsToday / max($grossToday, 1) > 0.02 ? 'warning' : 'success'),
            Stat::make('Cash 24h', number_format($cash24h, 2).' GEL')
                ->description('Settled via cash gateway'),
            Stat::make('Unsettled (7d)', $unsettled)
                ->description($unsettled > 0 ? 'Payments in failed state — needs ops review' : 'All clear')
                ->color($unsettled > 0 ? 'danger' : 'success'),
        ];
    }
}
