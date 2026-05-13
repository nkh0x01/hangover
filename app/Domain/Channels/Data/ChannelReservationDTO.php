<?php

namespace App\Domain\Channels\Data;

use App\Domain\Availability\Period;

/**
 * Provider-agnostic representation of an inbound reservation.
 *
 * Providers translate their wire format (Booking XML, Expedia JSON, iCal VEVENT, …)
 * into this DTO so the import pipeline only deals with one shape.
 */
final class ChannelReservationDTO
{
    public function __construct(
        public readonly string $externalId,
        public readonly string $externalRoomId,
        public readonly Period $period,
        public readonly string $guestFirstName,
        public readonly string $guestLastName,
        public readonly ?string $guestEmail,
        public readonly ?string $guestPhone,
        public readonly int $adults,
        public readonly int $children,
        public readonly ?float $total,
        public readonly string $currency,
        public readonly array $rawPayload,
        public readonly ?string $externalRateId = null,
        public readonly ?string $specialRequests = null,
    ) {
    }

    public function fingerprint(): string
    {
        return hash('sha256', json_encode([
            $this->externalId,
            $this->externalRoomId,
            $this->period->checkIn->toDateString(),
            $this->period->checkOut->toDateString(),
            $this->guestFirstName,
            $this->guestLastName,
            $this->guestEmail,
            $this->adults,
            $this->children,
            $this->total,
            $this->currency,
        ]));
    }
}
