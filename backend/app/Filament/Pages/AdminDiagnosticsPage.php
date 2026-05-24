<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Illuminate\Support\Facades\Route;

final class AdminDiagnosticsPage extends Page
{
    protected static ?string $navigationGroup = 'დიაგნოსტიკა';

    protected static ?string $navigationLabel = 'დიაგნოსტიკა';

    protected static ?string $navigationIcon = 'heroicon-o-wrench-screwdriver';

    protected static ?string $slug = 'diagnostics';

    protected static string $view = 'filament.pages.admin-diagnostics';

    protected static ?string $title = 'დიაგნოსტიკა';

    protected static ?int $navigationSort = 90;

    /**
     * @return array<string, string>
     */
    public function diagnostics(): array
    {
        return [
            'Environment' => app()->environment(),
            'App URL' => (string) config('app.url'),
            'Release' => (string) config('app.release', 'dev'),
            'API health' => url('/api/v1/health'),
            'Driver applications admin route' => Route::has('filament.admin.resources.driver-applications.index') ? 'registered' : 'missing',
            'Driver application API route' => Route::has('driver.application.show') ? 'registered' : 'missing',
            'Broadcast connection' => (string) config('broadcasting.default'),
            'Queue connection' => (string) config('queue.default'),
            'Cache store' => (string) config('cache.default'),
            'SMS driver' => (string) config('sms.driver', 'unknown'),
        ];
    }
}
