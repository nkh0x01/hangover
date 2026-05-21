<?php

declare(strict_types=1);

namespace App\Modules\Support\Filament\Widgets;

use App\Modules\Driver\Models\Driver;
use App\Modules\Driver\Models\DriverDocument;
use App\Modules\Support\Models\FraudFlag;
use App\Modules\Support\Models\SosEvent;
use App\Modules\Support\Models\SupportTicket;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Safety KPIs surfaced on the dashboard. Designed to make the
 * day-shift ops eye land on whichever number is non-green first.
 */
final class SafetyOverviewWidget extends BaseWidget
{
    protected static ?int $sort = 0;

    protected int|string|array $columnSpan = 'full';

    protected function getStats(): array
    {
        $openSos = SosEvent::query()->whereIn('status', ['open', 'acknowledged'])->count();
        $urgentTickets = SupportTicket::query()
            ->where('priority', 'urgent')
            ->whereIn('status', ['open', 'in_progress', 'waiting_user'])
            ->count();
        $openBlockingFlags = FraudFlag::query()
            ->where('severity', 'block')
            ->whereNull('resolved_at')
            ->count();
        $driversInReview = Driver::query()
            ->where('verification_status', 'in_review')
            ->count();
        $expiringSoon = DriverDocument::query()
            ->where('status', 'approved')
            ->whereNotNull('expires_on')
            ->where('expires_on', '<=', now()->addDays((int) config('safety.documents.expiry_warning_days', 30)))
            ->count();
        $verifiedDrivers = Driver::query()->where('verification_status', 'verified')->count();

        return [
            Stat::make('Open SOS', $openSos)
                ->description($openSos === 0 ? 'No emergency events' : 'Needs immediate attention')
                ->color($openSos === 0 ? 'success' : 'danger'),
            Stat::make('Urgent tickets', $urgentTickets)
                ->color($urgentTickets === 0 ? 'success' : 'warning'),
            Stat::make('Blocking fraud flags', $openBlockingFlags)
                ->color($openBlockingFlags === 0 ? 'success' : 'danger'),
            Stat::make('Drivers in review', $driversInReview)
                ->description($driversInReview === 0 ? 'No docs queued' : 'Review queue'),
            Stat::make('Docs expiring (30d)', $expiringSoon)
                ->color($expiringSoon > 0 ? 'warning' : 'success'),
            Stat::make('Verified drivers', $verifiedDrivers)
                ->color('success'),
        ];
    }
}
