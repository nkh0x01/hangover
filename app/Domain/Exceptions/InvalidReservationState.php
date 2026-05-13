<?php

namespace App\Domain\Exceptions;

use App\Models\Reservation;

class InvalidReservationState extends DomainException
{
    public static function cannotTransition(Reservation $reservation, string $to, string $reason = ''): self
    {
        return new self(sprintf(
            'Reservation %s cannot transition from "%s" to "%s"%s',
            $reservation->code,
            $reservation->status,
            $to,
            $reason !== '' ? ': '.$reason : '.',
        ));
    }
}
