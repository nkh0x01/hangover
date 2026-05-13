<?php

namespace App\Livewire\Reservations;

use App\Domain\Availability\AvailabilityService;
use App\Domain\Availability\Period;
use App\Domain\Exceptions\RoomNotAvailable;
use App\Domain\Pricing\PricingService;
use App\Domain\Reservations\Actions\CreateReservation;
use App\Domain\Reservations\Data\CreateReservationData;
use App\Models\Guest;
use App\Models\Property;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\RoomType;
use Carbon\CarbonImmutable;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Title('New Reservation')]
#[Layout('layouts.app')]
class Wizard extends Component
{
    public int $step = 1;

    // Step 1
    #[Url(as: 'date')]
    public ?string $checkIn = null;
    public ?string $checkOut = null;
    public int $adults = 2;
    public int $children = 0;

    // Step 2
    #[Url(as: 'room')]
    public ?int $roomId = null;
    public ?int $roomTypeId = null;

    // Step 3 — guest
    public ?int $guestId = null;
    public string $firstName = '';
    public string $lastName = '';
    public string $email = '';
    public string $phone = '';
    public string $country = '';
    public string $docType = 'passport';
    public string $docNumber = '';

    // Step 4
    public string $source = Reservation::SOURCE_DIRECT;
    public string $notes = '';
    public ?string $error = null;
    public ?int $createdReservationId = null;

    public function mount(): void
    {
        $today = CarbonImmutable::today();
        $this->checkIn  ??= $today->toDateString();
        $this->checkOut ??= $today->addDays(2)->toDateString();
    }

    public function nextStep(): void
    {
        if ($this->step === 1) {
            $this->validate([
                'checkIn'  => 'required|date',
                'checkOut' => 'required|date|after:checkIn',
                'adults'   => 'required|integer|min:1|max:8',
                'children' => 'required|integer|min:0|max:8',
            ]);
        }
        if ($this->step === 2 && ! $this->roomId) {
            $this->addError('roomId', 'Pick a room.');
            return;
        }
        if ($this->step === 3) {
            if (! $this->guestId) {
                $this->validate([
                    'firstName' => 'required|string|max:80',
                    'lastName'  => 'required|string|max:80',
                ]);
            }
        }
        $this->step = min(4, $this->step + 1);
    }

    public function prevStep(): void
    {
        $this->step = max(1, $this->step - 1);
    }

    public function pickRoom(int $roomId): void
    {
        $this->roomId = $roomId;
        $room = Room::find($roomId);
        $this->roomTypeId = $room?->room_type_id;
    }

    public function useExistingGuest(int $guestId): void
    {
        $this->guestId = $guestId;
    }

    public function clearExistingGuest(): void
    {
        $this->guestId = null;
    }

    public function create(): void
    {
        $this->error = null;

        $property = Property::query()->first();
        if (! $property) {
            $this->error = 'No property configured.';
            return;
        }

        $period = new Period($this->checkIn, $this->checkOut);
        $room   = Room::findOrFail($this->roomId);
        $type   = RoomType::findOrFail($room->room_type_id);

        $guest = $this->guestId
            ? Guest::findOrFail($this->guestId)
            : Guest::create([
                'property_id' => $property->id,
                'first_name'  => $this->firstName,
                'last_name'   => $this->lastName,
                'email'       => $this->email ?: null,
                'phone'       => $this->phone ?: null,
                'country'     => $this->country ?: null,
                'doc_type'    => $this->docNumber ? $this->docType : null,
                'doc_number'  => $this->docNumber ?: null,
                'language'    => app()->getLocale(),
            ]);

        try {
            $reservation = app(CreateReservation::class)->execute(new CreateReservationData(
                property:   $property,
                guest:      $guest,
                roomType:   $type,
                period:     $period,
                room:       $room,
                adults:     $this->adults,
                children:   $this->children,
                source:     $this->source,
                initialStatus: Reservation::STATUS_CONFIRMED,
                specialRequests: $this->notes ?: null,
                actor:      auth()->user(),
            ));
        } catch (RoomNotAvailable $e) {
            $this->error = $e->getMessage();
            return;
        } catch (\Throwable $e) {
            $this->error = $e->getMessage();
            return;
        }

        $this->createdReservationId = $reservation->id;
        $this->redirectRoute('reservations.show', $reservation);
    }

    public function render()
    {
        $property = Property::query()->first();

        // Step 2 — list rooms available for the chosen period
        $rooms = collect();
        $quote = null;
        $cells = collect();
        if ($property && $this->checkIn && $this->checkOut && $this->step >= 2) {
            $period = new Period($this->checkIn, $this->checkOut);
            $svc = app(AvailabilityService::class);
            $allRooms = $property->rooms()->with('roomType')->orderBy('number')->get();
            $rooms = $allRooms->map(function (Room $r) use ($svc, $period) {
                $r->is_available = $svc->isRoomAvailable($r, $period);
                return $r;
            });

            if ($this->roomId) {
                $room = $rooms->firstWhere('id', $this->roomId);
                if ($room) {
                    $quote = app(PricingService::class)->priceForStay($room->roomType, $period);
                }
            }
        }

        // Step 3 — guest search
        $guestSuggestions = collect();
        if ($this->step === 3 && $property && (mb_strlen($this->firstName) >= 2 || mb_strlen($this->lastName) >= 2 || mb_strlen($this->phone) >= 3)) {
            $q = Guest::query()->where('property_id', $property->id);
            if ($this->firstName) $q->where('first_name', 'like', $this->firstName.'%');
            if ($this->lastName)  $q->where('last_name', 'like', $this->lastName.'%');
            if ($this->phone)     $q->orWhere('phone', 'like', '%'.$this->phone.'%');
            $guestSuggestions = $q->limit(6)->get();
        }

        return view('livewire.reservations.wizard', [
            'rooms'             => $rooms,
            'quote'             => $quote,
            'guestSuggestions'  => $guestSuggestions,
            'currency'          => $property?->base_currency ?? 'USD',
            'nights'            => $this->checkIn && $this->checkOut
                ? (new Period($this->checkIn, $this->checkOut))->nightCount()
                : 0,
        ]);
    }
}
