<?php

namespace App\Domain\Channels\Support;

use App\Domain\Channels\Contracts\ChannelProviderInterface;
use App\Models\ChannelConnection;
use Illuminate\Container\Container;
use RuntimeException;

/**
 * Resolves the right provider implementation for a given ChannelConnection.
 * Keeps ChannelSyncService free of provider knowledge — it just asks the
 * registry for "whatever Booking.com provider is bound today" and trusts
 * the container.
 */
class ProviderRegistry
{
    /**
     * @param  array<string, class-string<ChannelProviderInterface>>  $providers
     */
    public function __construct(
        private readonly array $providers,
        private readonly Container $container,
    ) {
    }

    public function forConnection(ChannelConnection $connection): ChannelProviderInterface
    {
        return $this->forKey($connection->channel);
    }

    public function forKey(string $key): ChannelProviderInterface
    {
        $class = $this->providers[$key] ?? null;
        if (! $class) {
            throw new RuntimeException("No channel provider registered for key={$key}.");
        }
        return $this->container->make($class);
    }

    /**
     * @return array<int, string>
     */
    public function keys(): array
    {
        return array_keys($this->providers);
    }
}
