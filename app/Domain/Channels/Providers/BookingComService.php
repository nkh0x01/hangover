<?php

namespace App\Domain\Channels\Providers;

use App\Domain\Availability\Period;
use App\Domain\Channels\Contracts\ChannelProviderInterface;
use App\Domain\Channels\Exceptions\ChannelProviderException;
use App\Models\ChannelConnection;

/**
 * Stub for Booking.com's Channel Manager API.
 *
 * Phase 4 ships ONLY the surface — every real call throws so that nothing
 * accidentally talks to production. The plan for the real implementation
 * lives in docs/channels/booking-com.md (to be added when we wire it up):
 *
 *   - Auth: hotel_id + secret stored in ChannelConnection.credentials (encrypted)
 *   - Pull: Booking pushes via webhooks (Reservations webhook payload)
 *           plus a periodic POST /reservations/list reconciliation pull.
 *   - Push: PUT /availability and PUT /rates against the Booking endpoint.
 *
 * Do NOT remove the throws here without the real implementation behind them.
 */
class BookingComService implements ChannelProviderInterface
{
    public function key(): string
    {
        return ChannelConnection::CHANNEL_BOOKING;
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
        return true;
    }

    public function supportsPushRestrictions(): bool
    {
        return true;
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
        throw $this->notImplemented(__FUNCTION__);
    }

    public function pushRestrictions(ChannelConnection $connection, array $rows): void
    {
        throw $this->notImplemented(__FUNCTION__);
    }

    private function notImplemented(string $method): ChannelProviderException
    {
        return new ChannelProviderException(
            "Booking.com integration is a Phase-5 task; {$method}() is not yet implemented.",
        );
    }
}
