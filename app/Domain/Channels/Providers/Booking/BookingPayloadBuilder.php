<?php

namespace App\Domain\Channels\Providers\Booking;

use App\Domain\Channels\Data\AvailabilityDTO;
use App\Domain\Channels\Data\RateDTO;
use App\Domain\Channels\Data\RestrictionDTO;
use App\Models\ChannelConnection;

/**
 * Builds the JSON payload bodies we'd send to Booking.com's Channel Manager
 * API. Kept separate from BookingComService so the same builder can serve
 * both the live HTTP call and the dry-run / "preview payload" UI without
 * re-implementing the shape.
 *
 * The actual Booking.com API speaks OTA-XML for some endpoints, JSON for
 * others. To keep Phase 5 cleanly previewable we emit a JSON envelope
 * that's a faithful 1:1 of the field names Booking uses; the live adapter
 * can wrap this in OTA-XML when we go live for real in Phase 6.
 */
class BookingPayloadBuilder
{
    /**
     * @param  array<int, AvailabilityDTO>  $rows
     */
    public function availability(ChannelConnection $connection, array $rows): array
    {
        return [
            'hotel_id' => $this->hotelId($connection),
            'sent_at' => now()->toIso8601String(),
            'availability' => array_map(fn (AvailabilityDTO $r) => [
                'room_id' => $r->externalRoomId,
                'date' => $r->date,
                'rooms_available' => $r->available,
            ], $rows),
        ];
    }

    /**
     * @param  array<int, RateDTO>  $rows
     */
    public function rates(ChannelConnection $connection, array $rows): array
    {
        return [
            'hotel_id' => $this->hotelId($connection),
            'sent_at' => now()->toIso8601String(),
            'rates' => array_map(fn (RateDTO $r) => [
                'room_id' => $r->externalRoomId,
                'rate_id' => $r->externalRateId,
                'date' => $r->date,
                'price' => $r->amount,
                'currency' => $r->currency,
            ], $rows),
        ];
    }

    /**
     * @param  array<int, RestrictionDTO>  $rows
     */
    public function restrictions(ChannelConnection $connection, array $rows): array
    {
        return [
            'hotel_id' => $this->hotelId($connection),
            'sent_at' => now()->toIso8601String(),
            'restrictions' => array_map(fn (RestrictionDTO $r) => [
                'room_id' => $r->externalRoomId,
                'date' => $r->date,
                'min_stay' => $r->minStay,
                'max_stay' => $r->maxStay,
                'closed_to_arrival' => $r->closedToArrival,
                'closed_to_departure' => $r->closedToDeparture,
                'stop_sell' => $r->stopSell,
            ], $rows),
        ];
    }

    public function pullQuery(ChannelConnection $connection, string $from, string $to): array
    {
        return [
            'hotel_id' => $this->hotelId($connection),
            'arrival_from' => $from,
            'arrival_to' => $to,
            'include_modifications' => true,
            'include_cancellations' => true,
        ];
    }

    private function hotelId(ChannelConnection $connection): string
    {
        $creds = $connection->credentials ?? [];
        return (string) ($creds['hotel_id'] ?? '');
    }
}
