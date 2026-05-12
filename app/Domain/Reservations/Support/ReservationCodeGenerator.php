<?php

namespace App\Domain\Reservations\Support;

use App\Models\Property;
use App\Models\Reservation;
use Illuminate\Support\Str;

class ReservationCodeGenerator
{
    public function generate(Property $property): string
    {
        // R-<YY><MM>-XXXXXX — short enough to dictate over the phone,
        // unique under UNIQUE(property_id, code).
        for ($i = 0; $i < 8; $i++) {
            $code = sprintf(
                'R-%s-%s',
                now($property->timezone)->format('ym'),
                strtoupper(Str::random(6)),
            );

            $taken = Reservation::query()
                ->where('property_id', $property->id)
                ->where('code', $code)
                ->exists();

            if (! $taken) {
                return $code;
            }
        }

        throw new \RuntimeException('Could not generate a unique reservation code after 8 attempts.');
    }
}
