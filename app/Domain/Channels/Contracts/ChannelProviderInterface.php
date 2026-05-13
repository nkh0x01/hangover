<?php

namespace App\Domain\Channels\Contracts;

use App\Domain\Availability\Period;
use App\Domain\Channels\Data\AvailabilityDTO;
use App\Domain\Channels\Data\ChannelReservationDTO;
use App\Domain\Channels\Data\RateDTO;
use App\Domain\Channels\Data\RestrictionDTO;
use App\Models\ChannelConnection;

/**
 * The single contract every channel implementation (Mock, Booking.com,
 * Expedia, Airbnb iCal, …) implements. The orchestration layer
 * (ChannelSyncService) never talks to a provider's wire format directly —
 * it only goes through this interface.
 *
 * Phase 4 ships the Mock implementation plus stubs for the real providers.
 * Real OTA integrations belong in later phases.
 */
interface ChannelProviderInterface
{
    public function key(): string;

    public function supportsPullReservations(): bool;

    public function supportsPushAvailability(): bool;

    public function supportsPushRates(): bool;

    public function supportsPushRestrictions(): bool;

    /**
     * Cheap health check — does NOT mutate state on the remote side.
     */
    public function testConnection(ChannelConnection $connection): bool;

    /**
     * Returns the inbound reservations the provider has for us since (or
     * within) the given period. Implementations MUST be idempotent — calling
     * twice for the same window must yield the same external_id rows.
     *
     * @return iterable<int, ChannelReservationDTO>
     */
    public function pullReservations(ChannelConnection $connection, Period $window): iterable;

    /**
     * Push availability (rooms-left counts) for the given period.
     *
     * @param  array<int, AvailabilityDTO>  $rows
     */
    public function pushAvailability(ChannelConnection $connection, array $rows): void;

    /**
     * Push rate updates for the given period.
     *
     * @param  array<int, RateDTO>  $rows
     */
    public function pushRates(ChannelConnection $connection, array $rows): void;

    /**
     * Push stay restrictions (MinLOS, CTA/CTD, stop-sell).
     *
     * @param  array<int, RestrictionDTO>  $rows
     */
    public function pushRestrictions(ChannelConnection $connection, array $rows): void;
}
