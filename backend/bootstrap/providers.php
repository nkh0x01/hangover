<?php

declare(strict_types=1);
use App\Filament\AdminPanelProvider;
use App\Providers\AppServiceProvider;
use App\Providers\AuthServiceProvider;
use App\Providers\BroadcastServiceProvider;
use App\Providers\EventServiceProvider;
use App\Providers\HorizonServiceProvider;
use App\Providers\ModuleServiceProvider;
use App\Providers\TelescopeServiceProvider;

return [
    AppServiceProvider::class,
    AuthServiceProvider::class,
    BroadcastServiceProvider::class,
    EventServiceProvider::class,
    HorizonServiceProvider::class,
    ModuleServiceProvider::class,
    TelescopeServiceProvider::class,
    AdminPanelProvider::class,
];
