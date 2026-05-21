<?php

declare(strict_types=1);

namespace App\Modules\Riding\Filament\Pages;

use App\Modules\Riding\Filament\Widgets\PilotMetricsWidget;
use Filament\Pages\Page;

/**
 * Phase 2.2 pilot operations dashboard. Single-page view bundling:
 *
 *   - PilotMetricsWidget — throughput, quality, supply stats
 *   - Recent test rides table (rendered in the blade view)
 *   - Recent incident notes (placeholder — wired from support tickets
 *     in Phase 3)
 *
 * Visible only to users with the `pilot.view` permission (granted to
 * the Ops + Engineering roles by default — see config/permissions).
 */
final class PilotDashboardPage extends Page
{
    protected static ?string $navigationGroup = 'Operations';

    protected static ?string $navigationIcon = 'heroicon-o-rocket-launch';

    protected static string $view = 'filament.pages.pilot-dashboard';

    protected static ?string $title = 'Pilot dashboard';

    protected static ?int $navigationSort = 0;

    public ?string $pollingInterval = '15s';

    public static function canAccess(): bool
    {
        return (bool) config('pilot.enabled', false)
            || app()->environment(['local', 'staging']);
    }

    /**
     * @return array<class-string>
     */
    protected function getHeaderWidgets(): array
    {
        return [PilotMetricsWidget::class];
    }

    public function getHeaderWidgetsColumns(): int
    {
        return 3;
    }
}
