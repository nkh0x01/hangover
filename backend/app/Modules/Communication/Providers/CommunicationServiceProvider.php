<?php

declare(strict_types=1);

namespace App\Modules\Communication\Providers;

use App\Modules\Communication\Contracts\SmsGateway;
use Illuminate\Support\ServiceProvider;
use RuntimeException;

final class CommunicationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SmsGateway::class, function (): SmsGateway {
            $driver = (string) config('sms.driver', 'null');
            $cfg = (array) config("sms.drivers.$driver");

            if (! isset($cfg['class']) || ! class_exists($cfg['class'])) {
                throw new RuntimeException("Unknown SMS driver: {$driver}");
            }

            /** @var class-string<SmsGateway> $cls */
            $cls = $cfg['class'];

            return match ($driver) {
                'twilio' => new $cls(
                    accountSid: (string) ($cfg['sid'] ?? ''),
                    authToken: (string) ($cfg['token'] ?? ''),
                    from: (string) ($cfg['from'] ?? ''),
                ),
                default => new $cls,
            };
        });
    }

    public function boot(): void
    {
        // Push channel + notification templates land here in Phase 4.
    }
}
