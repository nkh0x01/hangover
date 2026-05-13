<?php

namespace App\Providers;

use App\Domain\Channels\Contracts\ChannelProviderInterface;
use App\Domain\Channels\Providers\AirbnbIcalService;
use App\Domain\Channels\Providers\BookingComService;
use App\Domain\Channels\Providers\ExpediaService;
use App\Domain\Channels\Providers\MockChannelService;
use App\Domain\Channels\Support\ProviderRegistry;
use App\Models\ChannelConnection;
use Illuminate\Support\ServiceProvider;

/**
 * Wires the channel domain into Laravel's container.
 *
 * Default binding for ChannelProviderInterface is the Mock provider — every
 * environment can run channel sync end-to-end without touching real OTA
 * credentials. ProviderRegistry maps channel keys to concrete classes so
 * ChannelSyncService can resolve "whatever provider is active for this
 * connection's `channel` column".
 */
class ChannelServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(ChannelProviderInterface::class, MockChannelService::class);

        $this->app->singleton(ProviderRegistry::class, function ($app) {
            return new ProviderRegistry(
                providers: [
                    ChannelConnection::CHANNEL_MOCK         => MockChannelService::class,
                    ChannelConnection::CHANNEL_BOOKING      => BookingComService::class,
                    ChannelConnection::CHANNEL_EXPEDIA      => ExpediaService::class,
                    ChannelConnection::CHANNEL_AIRBNB       => AirbnbIcalService::class,
                    ChannelConnection::CHANNEL_ICAL_GENERIC => AirbnbIcalService::class,
                ],
                container: $app,
            );
        });
    }
}
