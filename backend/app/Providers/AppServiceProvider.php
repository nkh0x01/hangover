<?php

declare(strict_types=1);

namespace App\Providers;

use App\Support\Idempotency\IdempotencyStore;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

final class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(IdempotencyStore::class, function ($app) {
            return new IdempotencyStore($app['cache.store']);
        });
    }

    public function boot(): void
    {
        Model::shouldBeStrict(! app()->isProduction());
        Model::unguard(false);

        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }

        $this->configureRateLimiters();
    }

    private function configureRateLimiters(): void
    {
        RateLimiter::for('api.default', function (Request $request) {
            return [
                Limit::perMinute(120)->by($request->user()?->id ?: $request->ip()),
            ];
        });

        RateLimiter::for('api.write', function (Request $request) {
            return [
                Limit::perMinute(60)->by($request->user()?->id ?: $request->ip()),
            ];
        });

        RateLimiter::for('auth.otp', function (Request $request) {
            $phone = (string) $request->input('phone', '');

            return [
                Limit::perMinute(3)->by('otp:ip:'.$request->ip()),
                Limit::perHour((int) config('sms.otp.per_phone_per_hour', 5))->by('otp:phone:'.$phone),
            ];
        });

        RateLimiter::for('auth.verify', function (Request $request) {
            $phone = (string) $request->input('phone', '');

            return [Limit::perMinutes(10, 6)->by('verify:phone:'.$phone)];
        });

        RateLimiter::for('auth.refresh', function (Request $request) {
            $device = (string) $request->header('X-Device-Id', '');

            return [Limit::perMinute(10)->by('refresh:'.$device.':'.$request->ip())];
        });

        RateLimiter::for('driver.location', function (Request $request) {
            return [Limit::perMinute(120)->by('loc:'.($request->user()?->id ?: $request->ip()))];
        });

        RateLimiter::for('rides.create', function (Request $request) {
            $userId = (string) $request->user()?->id;

            return [
                Limit::perMinute(5)->by('rides:'.$userId),
                Limit::perDay(30)->by('rides:daily:'.$userId),
            ];
        });

        RateLimiter::for('support.create', function (Request $request) {
            return [Limit::perDay(5)->by('support:'.$request->user()?->id)];
        });

        RateLimiter::for('sos.create', function (Request $request) {
            return [Limit::perHour(5)->by('sos:'.$request->user()?->id)];
        });
    }
}
