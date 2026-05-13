<?php

namespace App\Domain\Channels\Providers;

use App\Domain\Availability\Period;
use App\Domain\Channels\Contracts\ChannelProviderInterface;
use App\Domain\Channels\Exceptions\ChannelProviderException;
use App\Models\ChannelConnection;

/**
 * Stub for Expedia's QuickConnect / EQC API. See the BookingComService
 * docblock for the rationale: surface only, no live calls in Phase 4.
 */
class ExpediaService implements ChannelProviderInterface
{
    public function key(): string
    {
        return ChannelConnection::CHANNEL_EXPEDIA;
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

    public function pullCancellations(ChannelConnection $connection, Period $window): iterable
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
            "Expedia integration is a Phase-5 task; {$method}() is not yet implemented.",
        );
    }
}
