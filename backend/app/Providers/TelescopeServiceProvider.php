<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
use Laravel\Telescope\IncomingEntry;
use Laravel\Telescope\Telescope;
use Laravel\Telescope\TelescopeApplicationServiceProvider;

final class TelescopeServiceProvider extends TelescopeApplicationServiceProvider
{
    public function register(): void
    {
        if (! class_exists(Telescope::class)) {
            return;
        }
        if (! app()->environment(['local', 'staging', 'testing'])) {
            return;
        }

        Telescope::night();

        Telescope::filter(function (IncomingEntry $entry): bool {
            if ($this->app->environment('local')) {
                return true;
            }

            return $entry->isReportableException()
                || $entry->isFailedRequest()
                || $entry->isFailedJob()
                || $entry->isScheduledTask()
                || $entry->hasMonitoredTag();
        });
    }

    protected function gate(): void
    {
        Gate::define('viewTelescope', function ($user = null): bool {
            return $user !== null && method_exists($user, 'hasRole') && $user->hasRole('super_admin');
        });
    }
}
