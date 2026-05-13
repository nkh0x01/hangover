<?php

namespace App\Domain\Channels\Providers;

use App\Domain\Availability\Period;
use App\Domain\Channels\Contracts\ChannelProviderInterface;
use App\Domain\Channels\Exceptions\ChannelProviderException;
use App\Models\ChannelConnection;

/**
 * Placeholder for the Airbnb iCal sync.
 *
 * Airbnb doesn't expose a CRUD-style channel API for small hotels — instead
 * we publish/consume iCal (RFC 5545) feeds. The plan when this is wired up:
 *
 *   - Inbound: parse the host's exported feed (URL in credentials) on a
 *     cron, build ChannelReservationDTO rows, route through the importer.
 *   - Outbound: expose a per-listing read-only feed Airbnb can subscribe to,
 *     listing booked nights only. NO rates, NO restrictions (iCal can't
 *     carry them).
 *
 * Phase 4 leaves the surface in place so the UI/registry already know about
 * the channel; every operation throws until Phase 5.
 */
class AirbnbIcalService implements ChannelProviderInterface
{
    public function key(): string
    {
        return ChannelConnection::CHANNEL_AIRBNB;
    }

    public function supportsPullReservations(): bool
    {
        return true;
    }

    public function supportsPushAvailability(): bool
    {
        return true;
    }

    public function supportsPushRates(): bool
    {
        return false;
    }

    public function supportsPushRestrictions(): bool
    {
        return false;
    }

    public function testConnection(ChannelConnection $connection): bool
    {
        throw $this->notImplemented(__FUNCTION__);
    }

    public function pullReservations(ChannelConnection $connection, Period $window): iterable
    {
        throw $this->notImplemented(__FUNCTION__);
    }

    public function pushAvailability(ChannelConnection $connection, array $rows): void
    {
        throw $this->notImplemented(__FUNCTION__);
    }

    public function pushRates(ChannelConnection $connection, array $rows): void
    {
        throw new ChannelProviderException('Airbnb iCal does not support rate push.');
    }

    public function pushRestrictions(ChannelConnection $connection, array $rows): void
    {
        throw new ChannelProviderException('Airbnb iCal does not support restriction push.');
    }

    private function notImplemented(string $method): ChannelProviderException
    {
        return new ChannelProviderException(
            "Airbnb iCal sync is a Phase-5 task; {$method}() is not yet implemented.",
        );
    }
}
