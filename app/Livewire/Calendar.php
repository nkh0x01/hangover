<?php

namespace App\Livewire;

use App\Domain\Availability\AvailabilityService;
use App\Domain\Availability\Period;
use App\Models\Property;
use App\Models\Reservation;
use Carbon\CarbonImmutable;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Title('Calendar')]
#[Layout('layouts.app')]
class Calendar extends Component
{
    #[Url(as: 'start')]
    public ?string $startDate = null;

    public int $daysCount = 30;

    public function mount(): void
    {
        $this->startDate ??= CarbonImmutable::today()->toDateString();
    }

    public function shift(int $deltaDays): void
    {
        $this->startDate = CarbonImmutable::parse($this->startDate)->addDays($deltaDays)->toDateString();
    }

    public function gotoToday(): void
    {
        $this->startDate = CarbonImmutable::today()->toDateString();
    }

    public function render()
    {
        $property = Property::query()->with('rooms')->first();
        $start = CarbonImmutable::parse($this->startDate);
        $period = new Period($start->toDateString(), $start->addDays($this->daysCount)->toDateString());

        $matrix = app(AvailabilityService::class)->matrix($property, $period);

        // Map reservation_id → reservation summary for tooltip / click target
        $reservationIds = collect($matrix)
            ->flatMap(fn ($row) => collect($row)->pluck('reservation_id'))
            ->filter()
            ->unique()
            ->values();

        $reservations = Reservation::query()
            ->whereIn('id', $reservationIds)
            ->with('leadGuest')
            ->get()
            ->keyBy('id');

        $rooms = $property?->rooms()->orderBy('number')->get() ?? collect();

        return view('livewire.calendar', [
            'property'     => $property,
            'rooms'        => $rooms,
            'days'         => collect($period->nightDates()),
            'matrix'       => $matrix,
            'reservations' => $reservations,
            'start'        => $start,
        ]);
    }
}
