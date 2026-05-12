<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

/**
 * Module-owned event → listener mappings are registered inside each
 * module's service provider via Event::listen(). Kept as a placeholder
 * provider so the bootstrap providers.php remains stable across phases.
 */
final class EventServiceProvider extends ServiceProvider
{
    public function boot(): void {}
}
