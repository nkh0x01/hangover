<?php

namespace App\Domain\Channels\Data;

/**
 * One row pushed to a provider: how many of an external room type are sellable
 * on a given date. The mapping layer translates external_room_id → room_type_id
 * before the push so providers stay agnostic of our internal IDs.
 */
final class AvailabilityDTO
{
    public function __construct(
        public readonly string $externalRoomId,
        public readonly string $date,    // Y-m-d
        public readonly int $available,
    ) {
    }

    public function toArray(): array
    {
        return [
            'external_room_id' => $this->externalRoomId,
            'date' => $this->date,
            'available' => $this->available,
        ];
    }
}
