<?php

declare(strict_types=1);

namespace App\Modules\Payment\Providers;

use App\Modules\Payment\Actions\IssueCancellationFee;
use App\Modules\Payment\Actions\IssueRideRefund;
use App\Modules\Payment\Actions\SettleRidePayment;
use App\Modules\Payment\Services\MoneyAuditLogger;
use App\Modules\Payment\Services\PaymentGatewayManager;
use App\Modules\Payment\Services\RideReceiptGenerator;
use Illuminate\Support\ServiceProvider;

final class PaymentServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(PaymentGatewayManager::class);
        $this->app->singleton(RideReceiptGenerator::class);
        $this->app->singleton(MoneyAuditLogger::class);

        // Actions are stateless / cheap to construct; binding them
        // as singletons keeps the dependency graph clean in tests.
        $this->app->singleton(SettleRidePayment::class);
        $this->app->singleton(IssueRideRefund::class);
        $this->app->singleton(IssueCancellationFee::class);
    }

    public function boot(): void {}
}
