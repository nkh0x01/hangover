<?php

declare(strict_types=1);

namespace App\Modules\Riding\Filament\Pages;

use App\Modules\Driver\Filament\Resources\DriverApplicationResource;
use App\Modules\Driver\Filament\Resources\DriverResource;
use App\Modules\Driver\Filament\Resources\VehicleResource;
use App\Modules\Driver\Models\Driver;
use App\Modules\Driver\Models\DriverApplication;
use App\Modules\Identity\Filament\Resources\UserResource;
use App\Modules\Identity\Models\User;
use App\Modules\Riding\Filament\Resources\RideResource;
use App\Modules\Riding\Filament\Widgets\PilotMetricsWidget;
use App\Modules\Riding\Models\Ride;
use Filament\Pages\Page;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

/**
 * Pilot operations dashboard for the Ride 360 admin panel.
 */
final class PilotDashboardPage extends Page
{
    protected static ?string $navigationGroup = 'პანელი';

    protected static ?string $navigationLabel = 'პანელი';

    protected static ?string $navigationIcon = 'heroicon-o-squares-2x2';

    protected static string $view = 'filament.pages.pilot-dashboard';

    protected static ?string $title = 'Ride 360 პანელი';

    protected static ?string $slug = 'pilot-dashboard-page';

    protected static ?int $navigationSort = 0;

    public ?string $pollingInterval = '15s';

    public static function canAccess(): bool
    {
        return (bool) config('pilot.enabled', false)
            || app()->environment(['local', 'staging', 'testing']);
    }

    /**
     * @return array<class-string>
     */
    protected function getHeaderWidgets(): array
    {
        return [PilotMetricsWidget::class];
    }

    public function getHeaderWidgetsColumns(): int
    {
        return 3;
    }

    /**
     * @return list<array{label: string, value: string, description: string, tone: string}>
     */
    public function healthCards(): array
    {
        return [
            [
                'label' => 'API სტატუსი',
                'value' => 'აქტიური',
                'description' => url('/api/v1/health'),
                'tone' => 'success',
            ],
            [
                'label' => 'განხილვის რიგი',
                'value' => (string) $this->safeCount(DriverApplication::class, fn ($query) => $query->whereIn('status', ['submitted', 'pending'])),
                'description' => 'გაგზავნილი/მოლოდინში მძღოლის განაცხადები',
                'tone' => 'warning',
            ],
            [
                'label' => 'დამტკიცებული მძღოლები',
                'value' => (string) $this->safeCount(Driver::class, fn ($query) => $query->where('status', 'approved')),
                'description' => 'მძღოლები, რომლებსაც ონლაინ გასვლა შეუძლიათ',
                'tone' => 'success',
            ],
            [
                'label' => 'დღევანდელი მგზავრობები',
                'value' => (string) $this->safeCount(Ride::class, fn ($query) => $query->where('requested_at', '>=', now()->startOfDay())),
                'description' => 'ყველა მოთხოვნა/მგზავრობა დღეს',
                'tone' => 'info',
            ],
        ];
    }

    /**
     * @return list<array{label: string, url: string, description: string, icon: string}>
     */
    public function quickLinks(): array
    {
        return [
            [
                'label' => 'მძღოლების განაცხადები',
                'url' => DriverApplicationResource::getUrl(),
                'description' => 'განიხილე ახალი რეგისტრაციები',
                'icon' => 'heroicon-o-clipboard-document-check',
            ],
            [
                'label' => 'მძღოლები',
                'url' => DriverResource::getUrl(),
                'description' => 'პროფილები, სტატუსები და ონლაინ მდგომარეობა',
                'icon' => 'heroicon-o-identification',
            ],
            [
                'label' => 'ტრანსპორტი',
                'url' => VehicleResource::getUrl(),
                'description' => 'მძღოლების აქტიური ტრანსპორტი',
                'icon' => 'heroicon-o-truck',
            ],
            [
                'label' => 'მგზავრობები',
                'url' => RideResource::getUrl(),
                'description' => 'მოთხოვნები და მიმდინარე მგზავრობები',
                'icon' => 'heroicon-o-map-pin',
            ],
            [
                'label' => 'მომხმარებლები',
                'url' => UserResource::getUrl(),
                'description' => 'კლიენტები, მძღოლები და ადმინისტრატორები',
                'icon' => 'heroicon-o-users',
            ],
            [
                'label' => 'დიაგნოსტიკა',
                'url' => url('/admin/diagnostics'),
                'description' => 'ტექნიკური სტატუსი და კონფიგურაცია',
                'icon' => 'heroicon-o-wrench-screwdriver',
            ],
        ];
    }

    /**
     * @return list<DriverApplication>
     */
    public function recentApplications(): array
    {
        if (! Schema::hasTable('driver_applications')) {
            return [];
        }

        return DriverApplication::query()
            ->with(['city', 'user'])
            ->latest('updated_at')
            ->limit(6)
            ->get()
            ->all();
    }

    /**
     * @return list<User>
     */
    public function recentUsers(): array
    {
        if (! Schema::hasTable('users')) {
            return [];
        }

        return User::query()
            ->latest('created_at')
            ->limit(6)
            ->get()
            ->all();
    }

    /**
     * @return list<Ride>
     */
    public function recentRides(): array
    {
        if (! Schema::hasTable('rides')) {
            return [];
        }

        return Ride::query()
            ->with(['customer', 'driver.user'])
            ->latest('requested_at')
            ->limit(6)
            ->get()
            ->all();
    }

    /**
     * @return list<string>
     */
    public function readinessWarnings(): array
    {
        $warnings = [];

        if (! config('pilot.enabled')) {
            $warnings[] = 'PILOT_ENABLED გამორთულია.';
        }

        if (blank(config('broadcasting.connections.reverb.key'))) {
            $warnings[] = 'Reverb key არ არის კონფიგურირებული.';
        }

        if (blank(config('geo.providers.google.key'))) {
            $warnings[] = 'Google Maps server key არ ჩანს კონფიგურაციაში.';
        }

        if (config('sms.driver') !== 'sender_ge') {
            $warnings[] = 'SMS driver sender_ge-ზე არ არის დაყენებული.';
        }

        if (! Route::has('filament.admin.resources.driver-applications.index')) {
            $warnings[] = '/admin/driver-applications route არ არის რეგისტრირებული.';
        }

        return $warnings;
    }

    /**
     * @return array<string, string>
     */
    public function diagnostics(): array
    {
        return [
            'Environment' => app()->environment(),
            'App URL' => (string) config('app.url'),
            'Release' => (string) config('app.release', 'dev'),
            'Broadcast' => (string) config('broadcasting.default'),
            'Queue' => (string) config('queue.default'),
            'Cache' => (string) config('cache.default'),
            'Database' => (string) config('database.default'),
            'Driver Applications route' => Route::has('filament.admin.resources.driver-applications.index') ? 'registered' : 'missing',
        ];
    }

    public function toneClass(string $tone): string
    {
        return match ($tone) {
            'success' => 'border-emerald-200 bg-emerald-50 text-emerald-800 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-200',
            'warning' => 'border-amber-200 bg-amber-50 text-amber-800 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-200',
            'info' => 'border-sky-200 bg-sky-50 text-sky-800 dark:border-sky-500/30 dark:bg-sky-500/10 dark:text-sky-200',
            default => 'border-gray-200 bg-gray-50 text-gray-800 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200',
        };
    }

    private function safeCount(string $model, callable $scope): int
    {
        /** @var class-string<Model> $model */
        $instance = new $model;

        if (! Schema::hasTable($instance->getTable())) {
            return 0;
        }

        try {
            return (int) $scope($model::query())->count();
        } catch (\Throwable) {
            return 0;
        }
    }
}
