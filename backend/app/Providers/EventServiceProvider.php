<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

final class EventServiceProvider extends ServiceProvider
{
    /**
     * The event → listener map at the application level. Per-module
     * mappings are registered inside the module's service provider via
     * Event::listen() to keep them co-located with their domain.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [];

    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
