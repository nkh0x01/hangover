<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Modules\Communication\Models\SmsLog;
use App\Modules\Driver\Models\Driver;
use App\Modules\Geo\Models\LiveLocation;
use App\Modules\Riding\Models\Ride;
use App\Modules\Riding\Models\RideOffer;
use Carbon\CarbonImmutable;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
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
        $redisReachable = $this->redisReachable();
        $latestLocation = $this->latestLocationTimestamp();

        return [
            'Environment' => app()->environment(),
            'App URL' => (string) config('app.url'),
            'Release' => (string) config('app.release', 'dev'),
            'API health' => url('/api/v1/health'),
            'Driver applications admin route' => Route::has('filament.admin.resources.driver-applications.index') ? 'registered' : 'missing',
            'Driver application API route' => Route::has('driver.application.show') ? 'registered' : 'missing',
            'Broadcast connection' => (string) config('broadcasting.default'),
            'Queue connection' => (string) config('queue.default'),
            'Queue names' => implode(',', array_filter((array) config('queue.queues', []))),
            'Queue pending jobs' => (string) $this->pendingJobsCount(),
            'Queue pending realtime jobs' => (string) $this->pendingJobsCount('realtime'),
            'Queue failed jobs' => (string) $this->failedJobsCount(),
            'Queue latest failed job' => $this->latestFailedJobSummary(),
            'Queue latest realtime job' => $this->latestRealtimeJobTimestamp() ?? 'none',
            'Queue worker processes' => $this->queueWorkerProcessCount(),
            'Queue worker warning' => $this->queueWorkerWarning(),
            'Cache store' => (string) config('cache.default'),
            'Redis geo connection' => (string) config('geo.index.connection', 'geo'),
            'Redis status' => $redisReachable ? 'reachable' : 'unreachable',
            'Geo fallback active' => $redisReachable ? 'no' : 'yes',
            'Geo fallback recent window' => (string) config('geo.index.fallback_recent_seconds', 300).'s',
            'Online drivers' => (string) $this->onlineDriversCount(),
            'Latest driver location' => $latestLocation ?? 'none',
            'Recent dispatch offers' => (string) $this->recentOfferCount(),
            'Recent no-driver rides' => (string) $this->recentNoDriverCount(),
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

    private function redisReachable(): bool
    {
        if (! config('geo.index.enabled', true)) {
            return false;
        }

        try {
            Redis::connection((string) config('geo.index.connection', 'geo'))->ping();

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    private function onlineDriversCount(): int
    {
        if (! Schema::hasTable('drivers')) {
            return 0;
        }

        return Driver::query()->where('online', true)->count();
    }

    private function latestLocationTimestamp(): ?string
    {
        if (! Schema::hasTable('live_locations')) {
            return null;
        }

        $timestamp = LiveLocation::query()->max('recorded_at');
        if ($timestamp === null) {
            return null;
        }

        return CarbonImmutable::parse($timestamp)
            ->timezone(config('app.timezone'))
            ->format('Y-m-d H:i:s');
    }

    private function recentOfferCount(): int
    {
        if (! Schema::hasTable('ride_offers')) {
            return 0;
        }

        return RideOffer::query()
            ->where('offered_at', '>=', now()->subMinutes(30))
            ->count();
    }

    private function recentNoDriverCount(): int
    {
        if (! Schema::hasTable('rides')) {
            return 0;
        }

        return Ride::query()
            ->where('status', 'no_drivers')
            ->where('updated_at', '>=', now()->subMinutes(30))
            ->count();
    }

    private function pendingJobsCount(?string $queue = null): int
    {
        if (! Schema::hasTable('jobs')) {
            return 0;
        }

        $query = DB::table('jobs');
        if ($queue !== null) {
            $query->where('queue', $queue);
        }

        return (int) $query->count();
    }

    private function failedJobsCount(): int
    {
        if (! Schema::hasTable('failed_jobs')) {
            return 0;
        }

        return (int) DB::table('failed_jobs')->count();
    }

    private function latestFailedJobSummary(): string
    {
        if (! Schema::hasTable('failed_jobs')) {
            return 'table missing';
        }

        $row = DB::table('failed_jobs')->latest('id')->first(['queue', 'failed_at']);
        if (! $row) {
            return 'none';
        }

        return sprintf(
            '%s on %s',
            $row->failed_at ? CarbonImmutable::parse($row->failed_at)->timezone(config('app.timezone'))->format('Y-m-d H:i:s') : 'unknown time',
            $row->queue ?: 'unknown queue',
        );
    }

    private function latestRealtimeJobTimestamp(): ?string
    {
        if (! Schema::hasTable('jobs')) {
            return null;
        }

        $timestamp = DB::table('jobs')->where('queue', 'realtime')->max('created_at');
        if ($timestamp === null) {
            return null;
        }

        return CarbonImmutable::createFromTimestamp((int) $timestamp)
            ->timezone(config('app.timezone'))
            ->format('Y-m-d H:i:s');
    }

    private function queueWorkerProcessCount(): string
    {
        $count = $this->queueWorkerProcesses();

        return $count === null ? 'unknown' : (string) $count;
    }

    private function queueWorkerWarning(): string
    {
        $count = $this->queueWorkerProcesses();

        if ($count === null) {
            return 'process check unavailable';
        }

        return $count > 0 ? 'none' : 'worker missing';
    }

    private function queueWorkerProcesses(): ?int
    {
        if (! function_exists('shell_exec')) {
            return null;
        }

        $output = @shell_exec(
            'for pid in $(pgrep -f "artisan queue:work database" 2>/dev/null); do args=$(ps -p "$pid" -o args= 2>/dev/null); case "$args" in "/opt/cpanel/ea-php84/root/usr/bin/php artisan queue:work database "*) case "$args" in *"--queue=realtime,default"*) echo "$pid";; esac;; esac; done',
        );
        if ($output === null) {
            return null;
        }

        $pids = array_filter(array_map('trim', explode("\n", $output)));

        return count($pids);
    }
}
