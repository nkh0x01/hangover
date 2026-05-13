<?php

namespace App\Livewire\Pricing;

use App\Models\PricingRule;
use App\Models\Property;
use App\Models\RoomType;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Pricing rules')]
#[Layout('layouts.app')]
class Rules extends Component
{
    public bool $showForm = false;
    public ?int $editingId = null;

    public string $name = '';
    public string $type = PricingRule::TYPE_WEEKEND;
    public int $priority = 100;
    public string $scope = PricingRule::SCOPE_PROPERTY;
    public ?int $roomTypeId = null;
    public bool $active = true;

    // Action
    public string $actionType = 'percent';  // percent | absolute | set
    public float $actionValue = 10;

    // Conditions
    public array $days = [5, 6];           // weekend
    public string $datesCsv = '';          // holiday: comma-separated
    public ?float $minOcc = null;          // occupancy
    public ?float $maxOcc = null;
    public ?int $maxDaysToArrival = null;  // last_minute
    public ?int $minNights = null;         // length_of_stay

    public ?string $validFrom = null;
    public ?string $validTo = null;

    public function openCreate(): void
    {
        $this->resetForm();
        $this->showForm = true;
    }

    public function openEdit(int $id): void
    {
        $r = PricingRule::findOrFail($id);
        $this->editingId = $r->id;
        $this->name = $r->name;
        $this->type = $r->type;
        $this->priority = (int) $r->priority;
        $this->scope = $r->scope;
        $this->roomTypeId = $r->room_type_id;
        $this->active = (bool) $r->active;
        $this->actionType = $r->action['type'] ?? 'percent';
        $this->actionValue = (float) ($r->action['value'] ?? 0);
        $this->days = $r->conditions['days'] ?? [5, 6];
        $this->datesCsv = implode(', ', $r->conditions['dates'] ?? []);
        $this->minOcc = $r->conditions['min_occ'] ?? null;
        $this->maxOcc = $r->conditions['max_occ'] ?? null;
        $this->maxDaysToArrival = $r->conditions['max_days_to_arrival'] ?? null;
        $this->minNights = $r->conditions['min_nights'] ?? null;
        $this->validFrom = $r->valid_from?->toDateString();
        $this->validTo = $r->valid_to?->toDateString();
        $this->showForm = true;
    }

    public function save(): void
    {
        $this->validate([
            'name'        => 'required|string|max:120',
            'type'        => 'required|in:'.implode(',', PricingRule::TYPES),
            'priority'    => 'required|integer|min:1|max:9999',
            'scope'       => 'required|in:'.implode(',', PricingRule::SCOPES),
            'actionType'  => 'required|in:percent,absolute,set',
            'actionValue' => 'required|numeric',
        ]);

        $property = Property::query()->first();
        $conditions = $this->buildConditions();

        $payload = [
            'property_id' => $property->id,
            'name'        => $this->name,
            'type'        => $this->type,
            'priority'    => $this->priority,
            'scope'       => $this->scope,
            'room_type_id' => $this->scope === PricingRule::SCOPE_ROOM_TYPE ? $this->roomTypeId : null,
            'conditions'  => $conditions,
            'action'      => ['type' => $this->actionType, 'value' => $this->actionValue],
            'valid_from'  => $this->validFrom ?: null,
            'valid_to'    => $this->validTo ?: null,
            'active'      => $this->active,
        ];

        if ($this->editingId) {
            PricingRule::findOrFail($this->editingId)->update($payload);
            $this->dispatch('toast', tone: 'ok', message: __('Pricing rule updated.'));
        } else {
            PricingRule::create($payload);
            $this->dispatch('toast', tone: 'ok', message: __('Pricing rule created.'));
        }
        $this->showForm = false;
    }

    public function toggle(int $id): void
    {
        $r = PricingRule::findOrFail($id);
        $r->update(['active' => ! $r->active]);
        $this->dispatch('toast', tone: 'ok',
            message: $r->active ? __('Rule enabled.') : __('Rule disabled.'),
        );
    }

    public function delete(int $id): void
    {
        PricingRule::findOrFail($id)->delete();
        $this->dispatch('toast', tone: 'warn', message: __('Rule deleted.'));
    }

    private function resetForm(): void
    {
        $this->reset(['editingId', 'name', 'priority', 'roomTypeId',
            'datesCsv', 'minOcc', 'maxOcc', 'maxDaysToArrival', 'minNights',
            'validFrom', 'validTo']);
        $this->type = PricingRule::TYPE_WEEKEND;
        $this->scope = PricingRule::SCOPE_PROPERTY;
        $this->priority = 100;
        $this->actionType = 'percent';
        $this->actionValue = 10;
        $this->days = [5, 6];
        $this->active = true;
    }

    private function buildConditions(): array
    {
        return match ($this->type) {
            PricingRule::TYPE_WEEKEND        => ['days' => array_values(array_map('intval', $this->days))],
            PricingRule::TYPE_SEASONAL       => [],
            PricingRule::TYPE_HOLIDAY        => ['dates' => array_filter(array_map('trim', explode(',', $this->datesCsv)))],
            PricingRule::TYPE_OCCUPANCY      => array_filter(['min_occ' => $this->minOcc, 'max_occ' => $this->maxOcc], fn ($v) => $v !== null),
            PricingRule::TYPE_LAST_MINUTE    => array_filter(['max_days_to_arrival' => $this->maxDaysToArrival], fn ($v) => $v !== null),
            PricingRule::TYPE_LENGTH_OF_STAY => array_filter(['min_nights' => $this->minNights], fn ($v) => $v !== null),
        };
    }

    public function render()
    {
        $property = Property::query()->first();
        $rules = PricingRule::query()
            ->where('property_id', $property?->id)
            ->with('roomType')
            ->orderBy('priority')
            ->orderBy('id')
            ->get();

        $roomTypes = RoomType::query()
            ->where('property_id', $property?->id)
            ->orderBy('name')
            ->get();

        return view('livewire.pricing.rules', compact('rules', 'roomTypes'));
    }
}
