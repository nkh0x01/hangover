<?php

namespace App\Livewire\Pricing;

use App\Domain\Availability\Period;
use App\Domain\Pricing\PricingService;
use App\Models\DailyRoomPrice;
use App\Models\Property;
use App\Models\RoomType;
use Carbon\CarbonImmutable;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Manager-facing pricing calendar. Shows room types × dates with the
 * engine-computed price per cell. Clicking a cell opens an inline editor
 * to set a manual override price + per-day restrictions.
 */
#[Title('Pricing calendar')]
#[Layout('layouts.app')]
class Calendar extends Component
{
    #[Url(as: 'start')]
    public ?string $startDate = null;
    public int $daysCount = 30;

    public ?int $editingRoomTypeId = null;
    public ?string $editingDate = null;
    public ?float $editPrice = null;
    public ?int $editMinStay = null;
    public bool $editCta = false;
    public bool $editCtd = false;

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

    public function edit(int $roomTypeId, string $date): void
    {
        $this->editingRoomTypeId = $roomTypeId;
        $this->editingDate = $date;
        $row = DailyRoomPrice::query()
            ->where('room_type_id', $roomTypeId)
            ->whereNull('room_id')
            ->whereIn('date', [$date])
            ->first();
        $this->editPrice = $row?->price ? (float) $row->price : null;
        $this->editMinStay = $row?->min_stay;
        $this->editCta = (bool) $row?->closed_to_arrival;
        $this->editCtd = (bool) $row?->closed_to_departure;
    }

    public function cancelEdit(): void
    {
        $this->editingRoomTypeId = null;
        $this->editingDate = null;
        $this->editPrice = null;
        $this->editMinStay = null;
        $this->editCta = false;
        $this->editCtd = false;
    }

    public function save(): void
    {
        if (! $this->editingRoomTypeId || ! $this->editingDate) {
            return;
        }
        $type = RoomType::findOrFail($this->editingRoomTypeId);

        // Empty override = delete the row.
        $isEmpty = $this->editPrice === null
            && $this->editMinStay === null
            && ! $this->editCta && ! $this->editCtd;

        $row = DailyRoomPrice::query()
            ->where('room_type_id', $type->id)
            ->whereNull('room_id')
            ->whereIn('date', [$this->editingDate])
            ->first();

        if ($isEmpty) {
            $row?->delete();
            $this->dispatch('toast', tone: 'ok', message: __('Override removed.'));
        } else {
            $payload = [
                'property_id'  => $type->property_id,
                'room_type_id' => $type->id,
                'room_id'      => null,
                'date'         => $this->editingDate,
                'price'        => $this->editPrice,
                'min_stay'     => $this->editMinStay,
                'closed_to_arrival'   => $this->editCta,
                'closed_to_departure' => $this->editCtd,
                'source'       => DailyRoomPrice::SOURCE_MANUAL,
            ];
            if ($row) {
                $row->update($payload);
            } else {
                DailyRoomPrice::create($payload);
            }
            $this->dispatch('toast', tone: 'ok', message: __('Override saved.'));
        }

        $this->cancelEdit();
    }

    public function render()
    {
        $property = Property::query()->first();
        $start = CarbonImmutable::parse($this->startDate);
        $period = new Period($start->toDateString(), $start->addDays($this->daysCount)->toDateString());

        $types = $property?->roomTypes()->orderBy('name')->get() ?? collect();
        $days = collect($period->nightDates());

        $service = app(PricingService::class);

        // Load overrides for the visible window in one query.
        $overrides = DailyRoomPrice::query()
            ->where('property_id', $property?->id)
            ->whereIn('date', $days->all())
            ->get()
            ->groupBy(['room_type_id', fn ($r) => $r->date->toDateString()]);

        // Build the visible matrix [room_type_id][date] => ['price' => x, 'manual' => bool, 'min' => n, 'cta' => bool, 'ctd' => bool]
        $matrix = [];
        foreach ($types as $type) {
            foreach ($days as $d) {
                $rate = $service->priceForNight($type, $d);
                $row  = $overrides[$type->id][$d][0] ?? null;
                $matrix[$type->id][$d] = [
                    'price'   => $rate->amount,
                    'manual'  => $row && $row->price !== null,
                    'min'     => $row?->min_stay,
                    'cta'     => (bool) $row?->closed_to_arrival,
                    'ctd'     => (bool) $row?->closed_to_departure,
                ];
            }
        }

        return view('livewire.pricing.calendar', [
            'types'    => $types,
            'days'     => $days,
            'matrix'   => $matrix,
            'start'    => $start,
            'currency' => $property?->base_currency ?? '',
        ]);
    }
}
