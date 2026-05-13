<?php

namespace App\Livewire\Pricing;

use App\Domain\Availability\Period;
use App\Models\DailyRoomPrice;
use App\Models\Property;
use Carbon\CarbonImmutable;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Restrictions-only view: same room_type × day grid as the pricing
 * calendar, but focused on min_stay / CTA / CTD. No prices visible —
 * the manager just toggles closed days and sets per-day minimum stays.
 */
#[Title('Restrictions')]
#[Layout('layouts.app')]
class Restrictions extends Component
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

    public function toggleCta(int $roomTypeId, string $date): void
    {
        $this->toggleFlag($roomTypeId, $date, 'closed_to_arrival');
    }

    public function toggleCtd(int $roomTypeId, string $date): void
    {
        $this->toggleFlag($roomTypeId, $date, 'closed_to_departure');
    }

    public function setMinStay(int $roomTypeId, string $date, ?int $value): void
    {
        $row = $this->resolveRow($roomTypeId, $date);
        $row->min_stay = $value && $value > 0 ? $value : null;
        $row->save();
        $this->dispatch('toast', tone: 'ok', message: __('Restriction saved.'));
    }

    private function toggleFlag(int $roomTypeId, string $date, string $column): void
    {
        $row = $this->resolveRow($roomTypeId, $date);
        $row->{$column} = ! $row->{$column};
        $row->save();
        $this->dispatch('toast', tone: 'ok', message: __('Restriction saved.'));
    }

    private function resolveRow(int $roomTypeId, string $date): DailyRoomPrice
    {
        $property = Property::query()->first();
        return DailyRoomPrice::firstOrNew(
            ['room_type_id' => $roomTypeId, 'room_id' => null, 'date' => $date],
            ['property_id' => $property?->id, 'source' => DailyRoomPrice::SOURCE_MANUAL],
        );
    }

    public function render()
    {
        $property = Property::query()->first();
        $start = CarbonImmutable::parse($this->startDate);
        $period = new Period($start->toDateString(), $start->addDays($this->daysCount)->toDateString());

        $types = $property?->roomTypes()->orderBy('name')->get() ?? collect();
        $days  = collect($period->nightDates());

        $rows = DailyRoomPrice::query()
            ->where('property_id', $property?->id)
            ->whereIn('date', $days->all())
            ->get()
            ->groupBy(['room_type_id', fn ($r) => $r->date->toDateString()]);

        $matrix = [];
        foreach ($types as $type) {
            foreach ($days as $d) {
                $row = $rows[$type->id][$d][0] ?? null;
                $matrix[$type->id][$d] = [
                    'min' => $row?->min_stay,
                    'cta' => (bool) $row?->closed_to_arrival,
                    'ctd' => (bool) $row?->closed_to_departure,
                ];
            }
        }

        return view('livewire.pricing.restrictions', compact('types', 'days', 'matrix', 'start'));
    }
}
