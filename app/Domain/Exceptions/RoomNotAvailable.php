<?php

namespace App\Domain\Exceptions;

use App\Domain\Availability\Period;

class RoomNotAvailable extends DomainException
{
    public static function forRoom(int $roomId, Period $period): self
    {
        return new self(sprintf(
            'Room #%d is not available for %s.',
            $roomId,
            (string) $period,
        ));
    }

    public static function forRoomType(int $roomTypeId, Period $period): self
    {
        return new self(sprintf(
            'No rooms of type #%d are available for %s.',
            $roomTypeId,
            (string) $period,
        ));
    }
}
