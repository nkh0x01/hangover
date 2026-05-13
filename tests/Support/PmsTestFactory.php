<?php

namespace Tests\Support;

use App\Domain\Availability\Period;
use App\Domain\Reservations\Actions\CreateReservation;
use App\Domain\Reservations\Data\CreateReservationData;
use App\Models\Guest;
use App\Models\Property;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use Carbon\CarbonImmutable;

/**
 * Lightweight builder that produces a fully-wired property → room types →
 * rooms → guest → optional reservation chain for tests. Saves a lot of
 * factory chaining in the action tests.
 */
class PmsTestFactory
{
    public Property $property;

    public RoomType $standardType;

    /** @var array<int, Room> */
    public array $rooms = [];

    public Guest $guest;

    public function __construct()
    {
        $this->property = Property::factory()->create([
            'timezone' => 'UTC',
            'base_currency' => 'USD',
            'settings' => ['invoice_number_prefix' => 'TST'],
        ]);

        $this->standardType = RoomType::factory()->create([
            'property_id' => $this->property->id,
            'name'        => 'Standard',
            'base_price'  => 100,
            'capacity_adults' => 2,
            'max_occupancy' => 2,
        ]);

        foreach (['101', '102'] as $num) {
            $this->rooms[] = Room::factory()->create([
                'property_id'  => $this->property->id,
                'room_type_id' => $this->standardType->id,
                'number'       => $num,
                'status'       => Room::STATUS_AVAILABLE,
            ]);
        }

        $this->guest = Guest::factory()->create(['property_id' => $this->property->id]);
    }

    public function room(int $index = 0): Room
    {
        return $this->rooms[$index];
    }

    /**
     * Build a non-weekend period starting "soon" so tests don't drift over weekends.
     */
    public function nextPeriod(int $offsetDays = 7, int $nights = 2): Period
    {
        $checkIn = CarbonImmutable::now('UTC')
            ->startOfDay()
            ->addDays($offsetDays)
            ->next(\Carbon\CarbonInterface::MONDAY); // anchor to Monday

        return new Period(
            $checkIn->toDateString(),
            $checkIn->addDays($nights)->toDateString(),
        );
    }

    public function user(string $role = 'super_admin'): User
    {
        $user = User::factory()->create(['property_id' => $this->property->id]);
        if (! \Spatie\Permission\Models\Role::where('name', $role)->exists()) {
            \Spatie\Permission\Models\Role::create(['name' => $role]);
        }
        $user->assignRole($role);

        return $user;
    }

    public function createReservation(
        ?Period $period = null,
        ?Room $room = null,
        string $initialStatus = Reservation::STATUS_CONFIRMED,
    ): Reservation {
        $action = app(CreateReservation::class);
        return $action->execute(new CreateReservationData(
            property:   $this->property,
            guest:      $this->guest,
            roomType:   $this->standardType,
            period:     $period ?? $this->nextPeriod(),
            room:       $room ?? $this->rooms[0],
            adults:     1,
            initialStatus: $initialStatus,
        ));
    }
}
