<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Modules\Communication\Models\SmsLog;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

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
            'Sender.ge sender configured' => filled(config('sms.drivers.sender_ge.sender')) ? 'yes' : 'no',
            'Sender.ge key configured' => filled(config('sms.drivers.sender_ge.api_key')) ? 'yes' : 'no',
        ];
    }

    /**
     * @return array{sent: int, failed: int, total: int}
     */
    public function smsCounts(): array
    {
        if (! $this->smsLogHasDiagnostics()) {
            return ['sent' => 0, 'failed' => 0, 'total' => 0];
        }

        return [
            'sent' => SmsLog::query()->where('message_type', 'otp')->where('status', 'sent')->count(),
            'failed' => SmsLog::query()->where('message_type', 'otp')->where('status', 'failed')->count(),
            'total' => SmsLog::query()->where('message_type', 'otp')->count(),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function recentSmsAttempts(): array
    {
        if (! $this->smsLogHasDiagnostics()) {
            return [];
        }

        return SmsLog::query()
            ->latest('id')
            ->limit(15)
            ->get(['created_at', 'masked_phone', 'purpose', 'provider', 'status', 'error_reason', 'skip_reason'])
            ->map(fn (SmsLog $log): array => [
                'created_at' => $log->created_at?->timezone(config('app.timezone'))->format('Y-m-d H:i:s'),
                'phone' => $log->masked_phone ?: '***',
                'purpose' => $log->purpose,
                'provider' => $log->provider,
                'status' => $log->status,
                'error' => $log->skip_reason ?: $log->error_reason,
            ])
            ->all();
    }

    private function smsLogHasDiagnostics(): bool
    {
        return Schema::hasTable('sms_log')
            && Schema::hasColumn('sms_log', 'message_type')
            && Schema::hasColumn('sms_log', 'masked_phone');
    }
}
