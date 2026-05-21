<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

/**
 * Walks the modules registry from config/modules.php and registers /
 * boots each module's own service provider. Each module thus owns its
 * routes, migrations, channel auth, Filament resources, etc.
 */
final class ModuleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        foreach ($this->modules() as $provider) {
            $this->app->register($provider);
        }
    }

    /**
     * @return array<int, class-string>
     */
    private function modules(): array
    {
        /** @var array<int, class-string> $list */
        $list = (array) config('modules.enabled', []);

        return array_values(array_filter($list, fn (string $cls): bool => class_exists($cls)));
    }
}
