<?php

declare(strict_types=1);

namespace App\Modules\Erp\Providers;

use App\Modules\Erp\Integration\Contracts\IntegrationLogger;
use App\Modules\Erp\Integration\DatabaseIntegrationLogger;
use Illuminate\Support\ServiceProvider;

/**
 * Gadget ERP bounded context (retail/wholesale): branches, brands,
 * procurement, inventory, pricing, POS. Built as a continuation of the
 * Martva stack alongside the existing platform modules.
 */
final class ErpServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(IntegrationLogger::class, DatabaseIntegrationLogger::class);
    }

    public function boot(): void
    {
        //
    }
}
