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
use Filament\Support\Enums\MaxWidth;
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
            ->brandName('Ride 360 Admin')
            ->sidebarCollapsibleOnDesktop()
            ->maxContentWidth(MaxWidth::Full)
            ->colors([
                'primary' => Color::Emerald,
            ])
            ->discoverPages(
                in: app_path('Filament/Pages'),
                for: 'App\\Filament\\Pages',
            )
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
                NavigationGroup::make('პანელი')->collapsible(false),
                NavigationGroup::make('მძღოლები')->collapsible(false),
                NavigationGroup::make('ტრანსპორტი'),
                NavigationGroup::make('მგზავრობები'),
                NavigationGroup::make('მომხმარებლები'),
                NavigationGroup::make('ფინანსები'),
                NavigationGroup::make('უსაფრთხოება'),
                NavigationGroup::make('პარამეტრები'),
                NavigationGroup::make('დიაგნოსტიკა'),
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
