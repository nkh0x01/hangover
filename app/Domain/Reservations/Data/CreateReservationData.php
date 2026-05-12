<?php

namespace App\Domain\Reservations\Data;

use App\Domain\Availability\Period;
use App\Models\Guest;
use App\Models\Property;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;

final class CreateReservationData
{
    public function __construct(
        public readonly Property $property,
        public readonly Guest $guest,
        public readonly RoomType $roomType,
        public readonly Period $period,
        public readonly ?Room $room = null,
        public readonly int $adults = 1,
        public readonly int $children = 0,
        public readonly string $source = 'direct',
        public readonly ?string $externalReference = null,
        public readonly string $initialStatus = 'confirmed',
        public readonly ?string $specialRequests = null,
        public readonly ?string $internalNotes = null,
        public readonly ?User $actor = null,
    ) {
    }
}
