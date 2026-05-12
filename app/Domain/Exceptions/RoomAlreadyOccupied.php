<?php

namespace App\Domain\Exceptions;

use App\Models\Room;

class RoomAlreadyOccupied extends DomainException
{
    public static function for(Room $room): self
    {
        return new self(sprintf(
            'Room %s is currently in status "%s" and cannot accept a check-in.',
            $room->number,
            $room->status,
        ));
    }
}
