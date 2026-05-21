<?php

declare(strict_types=1);

namespace App\Modules\Communication\Providers;

use App\Modules\Communication\Contracts\PushGateway;
use App\Modules\Communication\Contracts\SmsGateway;
use App\Modules\Communication\Push\FirebasePushGateway;
use App\Modules\Communication\Push\NullPushGateway;
use Illuminate\Support\ServiceProvider;
use Kreait\Firebase\Contract\Messaging;
use RuntimeException;
use Throwable;

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
                'sender_ge' => new $cls(
                    apiKey: (string) ($cfg['api_key'] ?? ''),
                    sender: (string) ($cfg['sender'] ?? ''),
                    baseUrl: (string) ($cfg['base_url'] ?? ''),
                ),
                default => new $cls,
            };
        });

        $this->app->singleton(PushGateway::class, function (): PushGateway {
            // Resolve only if kreait/laravel-firebase is configured.
            // Test + local-dev environments fall back to the null gateway.
            $driver = (string) config('push.driver', 'null');
            if ($driver !== 'firebase') {
                return new NullPushGateway;
            }

            try {
                /** @var Messaging $messaging */
                $messaging = $this->app->make(Messaging::class);

                return new FirebasePushGateway($messaging);
            } catch (Throwable) {
                // Firebase creds not configured — degrade gracefully so a
                // misconfigured staging deploy doesn't take ride dispatch
                // down with it.
                return new NullPushGateway;
            }
        });
    }

    public function boot(): void {}
}
