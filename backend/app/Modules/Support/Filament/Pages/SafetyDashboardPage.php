<?php

declare(strict_types=1);

namespace App\Modules\Support\Filament\Pages;

use App\Modules\Support\Filament\Widgets\SafetyOverviewWidget;
use Filament\Pages\Page;

/**
 * Operations safety dashboard. Top-of-funnel for the on-call ops
 * agent: open SOS events, urgent complaints, blocking fraud flags,
 * driver-verification queue, expiring documents.
 *
 * Pollable every 15 s — appropriate for a same-day reaction window
 * without slamming the DB during quiet hours.
 */
final class SafetyDashboardPage extends Page
{
    protected static ?string $navigationGroup = 'უსაფრთხოება';

    protected static ?string $navigationLabel = 'უსაფრთხოების პანელი';

    protected static ?string $navigationIcon = 'heroicon-o-shield-check';

    protected static string $view = 'filament.pages.safety-dashboard';

    protected static ?string $title = 'უსაფრთხოების პანელი';

    protected static ?int $navigationSort = 2;

    public ?string $pollingInterval = '15s';

    /** @return array<class-string> */
    protected function getHeaderWidgets(): array
    {
        return [SafetyOverviewWidget::class];
    }

    public function getHeaderWidgetsColumns(): int
    {
        return 3;
    }
}
