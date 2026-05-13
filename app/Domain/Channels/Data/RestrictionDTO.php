<?php

namespace App\Domain\Channels\Data;

final class RestrictionDTO
{
    public function __construct(
        public readonly string $externalRoomId,
        public readonly string $date,    // Y-m-d
        public readonly ?int $minStay = null,
        public readonly ?int $maxStay = null,
        public readonly bool $closedToArrival = false,
        public readonly bool $closedToDeparture = false,
        public readonly bool $stopSell = false,
    ) {
    }

    public function toArray(): array
    {
        return [
            'external_room_id' => $this->externalRoomId,
            'date' => $this->date,
            'min_stay' => $this->minStay,
            'max_stay' => $this->maxStay,
            'closed_to_arrival' => $this->closedToArrival,
            'closed_to_departure' => $this->closedToDeparture,
            'stop_sell' => $this->stopSell,
        ];
    }
}
