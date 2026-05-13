<?php

namespace App\Livewire\Pricing;

use App\Models\DailyRoomPrice;
use App\Models\Property;
use App\Models\RoomType;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Bulk price/restriction editor: pick a date range + room type(s) +
 * what to set, hit apply. Upserts one daily_room_prices row per
 * (room_type, date) in the selected range, all inside a transaction.
 */
#[Title('Bulk price update')]
#[Layout('layouts.app')]
class Bulk extends Component
{
    public string $startDate = '';
    public string $endDate = '';
    /** @var array<int> */
    public array $roomTypeIds = [];

    public string $mode = 'set'; // set | percent | clear
    public ?float $value = null;

    public bool $applyRestrictions = false;
    public ?int $minStay = null;
    public ?int $maxStay = null;
    public bool $cta = false;
    public bool $ctd = false;

    public bool $weekendsOnly = false;

    public function mount(): void
    {
        $today = CarbonImmutable::today();
        $this->startDate = $today->toDateString();
        $this->endDate   = $today->addDays(13)->toDateString();
    }

    public function apply(): void
    {
        $this->validate([
            'startDate'   => 'required|date',
            'endDate'     => 'required|date|after_or_equal:startDate',
            'roomTypeIds' => 'array|min:1',
            'mode'        => 'required|in:set,percent,clear',
            'value'       => 'nullable|numeric',
        ]);

        $property = Property::query()->first();
        $start = CarbonImmutable::parse($this->startDate);
        $end   = CarbonImmutable::parse($this->endDate);

        if ($end->lt($start)) {
            $this->dispatch('toast', tone: 'error', message: __('End date must be after start date.'));
            return;
        }

        $types = RoomType::query()
            ->where('property_id', $property?->id)
            ->whereIn('id', $this->roomTypeIds)
            ->get();

        if ($types->isEmpty()) {
            $this->dispatch('toast', tone: 'warn', message: __('Select at least one room type.'));
            return;
        }

        $touched = 0;

        \DB::transaction(function () use ($types, $start, $end, &$touched) {
            foreach (CarbonPeriod::create($start, $end) as $date) {
                $dateStr = $date->format('Y-m-d');
                if ($this->weekendsOnly && ! in_array(CarbonImmutable::parse($dateStr)->dayOfWeekIso, [5, 6], true)) {
                    continue;
                }

                foreach ($types as $type) {
                    $existing = DailyRoomPrice::query()
                        ->where('room_type_id', $type->id)
                        ->whereNull('room_id')
                        ->where('date', $dateStr)
                        ->first();

                    if ($this->mode === 'clear') {
                        $existing?->delete();
                        $touched++;
                        continue;
                    }

                    $price = $existing?->price !== null ? (float) $existing->price : null;
                    if ($this->mode === 'set' && $this->value !== null) {
                        $price = (float) $this->value;
                    }
                    if ($this->mode === 'percent' && $this->value !== null) {
                        $price = round(((float) $type->base_price) * (1 + ((float) $this->value) / 100), 2);
                    }

                    $payload = [
                        'property_id' => $type->property_id,
                        'room_type_id' => $type->id,
                        'room_id' => null,
                        'date' => $dateStr,
                        'price' => $price,
                        'source' => DailyRoomPrice::SOURCE_MANUAL,
                    ];

                    if ($this->applyRestrictions) {
                        $payload['min_stay'] = $this->minStay;
                        $payload['max_stay'] = $this->maxStay;
                        $payload['closed_to_arrival']   = $this->cta;
                        $payload['closed_to_departure'] = $this->ctd;
                    }

                    if ($existing) {
                        $existing->update($payload);
                    } else {
                        DailyRoomPrice::create($payload);
                    }
                    $touched++;
                }
            }
        });

        $this->dispatch('toast', tone: 'ok',
            message: __(':n rows updated.', ['n' => $touched]),
        );
    }

    public function render()
    {
        $property = Property::query()->first();
        $types = RoomType::query()
            ->where('property_id', $property?->id)
            ->orderBy('name')
            ->get();

        return view('livewire.pricing.bulk', compact('types'));
    }
}
