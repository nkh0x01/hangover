<?php

namespace App\Providers;

use App\Services\Channels\ChannelManager;
use Illuminate\Support\ServiceProvider;

class ChannelServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ChannelManager::class);
    }
}
