<?php

namespace Database\Seeders;

use App\Domain\Availability\Period;
use App\Domain\Reservations\Actions\CheckInReservation;
use App\Domain\Reservations\Actions\CheckOutReservation;
use App\Domain\Reservations\Actions\CreateReservation;
use App\Domain\Reservations\Actions\RecordPayment;
use App\Domain\Reservations\Data\CreateReservationData;
use App\Models\Guest;
use App\Models\Payment;
use App\Models\Property;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

/**
 * Seeds the demo reservation lifecycle for the staging preview:
 *
 *   • A confirmed reservation arriving today  (you can check it in)
 *   • A reservation currently CHECKED-IN      (in-house, you can check out)
 *   • A reservation already CHECKED-OUT       (invoice + payment exist)
 *   • Two future confirmed reservations       (calendar has data)
 *
 * Idempotent: re-running the seeder against an already-seeded database is a
 * no-op. Skips entirely when the property's reservation table already has
 * five or more rows, so it never pollutes a working dataset.
 */
class DemoReservationsSeeder extends Seeder
{
    public function run(): void
    {
        // Demo reservations belong to staging / local previews. They would
        // otherwise drift the assertions in SeedingTest and similar suites.
        if (app()->environment('testing')) {
            return;
        }

        $property = Property::query()->orderBy('id')->first();
        if (! $property) {
            return;
        }
        if ($property->reservations()->count() >= 5) {
            return;
        }

        $admin = User::query()->where('email', 'admin@example.test')->first();
        $today = CarbonImmutable::now($property->timezone)->startOfDay();

        $guests = $this->seedGuests($property);
        $rooms = $this->resolveRooms($property);

        // 1. Already checked-OUT (3 nights ago → yesterday)
        $this->checkedOutReservation(
            $property,
            $guests['out'],
            $rooms['standard'],
            $today->subDays(3),
            $today->subDay(),
            $admin,
        );

        // 2. Currently checked-IN (yesterday → tomorrow)
        $this->checkedInReservation(
            $property,
            $guests['in'],
            $rooms['deluxe'],
            $today->subDay(),
            $today->addDay(),
            $admin,
        );

        // 3. Arriving today, confirmed (today → +3)
        $this->confirmedReservation(
            $property,
            $guests['arriving'],
            $rooms['twin'],
            $today,
            $today->addDays(3),
        );

        // 4. Future reservation (+20 → +23). Deliberately past the seeded
        //    +7 CTA day in PricingSeeder so this stays clean.
        $this->confirmedReservation(
            $property,
            $guests['future_a'],
            $rooms['family'],
            $today->addDays(20),
            $today->addDays(23),
        );

        // 5. Another future reservation (+25 → +27)
        $this->confirmedReservation(
            $property,
            $guests['future_b'],
            $rooms['standard_alt'],
            $today->addDays(25),
            $today->addDays(27),
        );
    }

    private function seedGuests(Property $property): array
    {
        return [
            'out' => Guest::firstOrCreate(
                ['email' => 'demo.out@hotel-tbilisi.test'],
                [
                    'property_id' => $property->id,
                    'first_name' => 'Nino',
                    'last_name' => 'Beridze',
                    'phone' => '+995 555 100 100',
                    'country' => 'GE',
                    'language' => 'ka',
                ],
            ),
            'in' => Guest::firstOrCreate(
                ['email' => 'demo.in@hotel-tbilisi.test'],
                [
                    'property_id' => $property->id,
                    'first_name' => 'Giorgi',
                    'last_name' => 'Kapanadze',
                    'phone' => '+995 555 200 200',
                    'country' => 'GE',
                    'language' => 'ka',
                ],
            ),
            'arriving' => Guest::firstOrCreate(
                ['email' => 'demo.arriving@hotel-tbilisi.test'],
                [
                    'property_id' => $property->id,
                    'first_name' => 'Anna',
                    'last_name' => 'Schmidt',
                    'phone' => '+49 30 1234 5678',
                    'country' => 'DE',
                    'language' => 'en',
                ],
            ),
            'future_a' => Guest::firstOrCreate(
                ['email' => 'demo.family@hotel-tbilisi.test'],
                [
                    'property_id' => $property->id,
                    'first_name' => 'Mariam',
                    'last_name' => 'Tsiklauri',
                    'phone' => '+995 555 300 300',
                    'country' => 'GE',
                    'language' => 'ka',
                ],
            ),
            'future_b' => Guest::firstOrCreate(
                ['email' => 'demo.future@hotel-tbilisi.test'],
                [
                    'property_id' => $property->id,
                    'first_name' => 'Luca',
                    'last_name' => 'Rossi',
                    'phone' => '+39 06 1234 5678',
                    'country' => 'IT',
                    'language' => 'en',
                ],
            ),
        ];
    }

    private function resolveRooms(Property $property): array
    {
        $byType = $property->roomTypes()->with('rooms')->get()->keyBy('slug');

        return [
            'standard'      => $byType['standard']->rooms->firstWhere('number', '101'),
            'standard_alt'  => $byType['standard']->rooms->firstWhere('number', '102'),
            'deluxe'        => $byType['deluxe']->rooms->firstWhere('number', '104'),
            'twin'          => $byType['twin']->rooms->firstWhere('number', '103'),
            'family'        => $byType['family']->rooms->firstWhere('number', '302'),
        ];
    }

    private function confirmedReservation(
        Property $property,
        Guest $guest,
        Room $room,
        CarbonImmutable $checkIn,
        CarbonImmutable $checkOut,
    ): Reservation {
        return app(CreateReservation::class)->execute(new CreateReservationData(
            property: $property,
            guest:    $guest,
            roomType: $room->roomType,
            period:   new Period($checkIn->toDateString(), $checkOut->toDateString()),
            room:     $room,
            adults:   2,
            children: 0,
            source:   'direct',
            initialStatus: Reservation::STATUS_CONFIRMED,
        ));
    }

    private function checkedInReservation(
        Property $property,
        Guest $guest,
        Room $room,
        CarbonImmutable $checkIn,
        CarbonImmutable $checkOut,
        ?User $actor,
    ): Reservation {
        $reservation = $this->confirmedReservation($property, $guest, $room, $checkIn, $checkOut);
        return app(CheckInReservation::class)->execute(
            $reservation,
            $actor,
            'Demo guest checked in for staging preview.',
        );
    }

    private function checkedOutReservation(
        Property $property,
        Guest $guest,
        Room $room,
        CarbonImmutable $checkIn,
        CarbonImmutable $checkOut,
        ?User $actor,
    ): Reservation {
        $reservation = $this->checkedInReservation($property, $guest, $room, $checkIn, $checkOut, $actor);

        // Pay the room nights, then check out — generates the invoice and
        // a completed payment row, exactly mirroring the real flow.
        app(RecordPayment::class)->execute(
            $reservation,
            Payment::METHOD_CARD,
            (float) $reservation->fresh()->room_rate_total,
            $actor,
            'DEMO-PAY-'.$reservation->code,
            'Demo card payment for staging preview.',
        );

        app(CheckOutReservation::class)->execute(
            $reservation->fresh(),
            $actor,
            [],
            'Demo guest checked out for staging preview.',
        );

        return $reservation->fresh();
    }
}
