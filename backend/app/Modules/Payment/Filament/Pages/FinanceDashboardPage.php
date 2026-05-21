<?php

declare(strict_types=1);

namespace App\Modules\Payment\Filament\Pages;

use App\Modules\Payment\Filament\Widgets\FinanceOverviewWidget;
use Filament\Pages\Page;

/**
 * Finance dashboard. Top-level KPIs, today's payments lane, and
 * pending payouts list. The resources `PaymentResource`,
 * `PayoutResource`, `RefundResource`, `WalletResource`,
 * `TransactionResource` provide the drill-downs.
 */
final class FinanceDashboardPage extends Page
{
    protected static ?string $navigationGroup = 'Finance';

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static string $view = 'filament.pages.finance-dashboard';

    protected static ?string $title = 'Finance overview';

    protected static ?int $navigationSort = 0;

    public ?string $pollingInterval = '30s';

    /** @return array<class-string> */
    protected function getHeaderWidgets(): array
    {
        return [FinanceOverviewWidget::class];
    }

    public function getHeaderWidgetsColumns(): int
    {
        return 3;
    }
}
