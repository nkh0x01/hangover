<?php

declare(strict_types=1);

namespace App\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

final class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('admin')
            ->path('admin')
            ->default()
            ->login()
            ->brandName('Hangover Mobility')
            ->colors([
                'primary' => Color::Emerald,
            ])
            ->discoverResources(
                in: app_path('Modules'),
                for: 'App\\Modules',
            )
            ->discoverPages(
                in: app_path('Modules'),
                for: 'App\\Modules',
            )
            ->discoverWidgets(
                in: app_path('Modules'),
                for: 'App\\Modules',
            )
            ->navigationGroups([
                NavigationGroup::make('Operations')->collapsible(false),
                NavigationGroup::make('Drivers'),
                NavigationGroup::make('Rides'),
                NavigationGroup::make('Customers'),
                NavigationGroup::make('Finance'),
                NavigationGroup::make('Pricing'),
                NavigationGroup::make('Promotions'),
                NavigationGroup::make('Support'),
                NavigationGroup::make('CMS'),
                NavigationGroup::make('System'),
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ])
            ->darkMode();
    }
}
