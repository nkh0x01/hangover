<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class ModulesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        foreach (config('modules.modules', []) as $module) {
            $this->app->register($module);
        }
    }
}
