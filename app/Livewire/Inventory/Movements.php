<?php

namespace App\Livewire\Inventory;

use App\Models\InventoryMovement;
use App\Models\Property;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Inventory movements')]
#[Layout('layouts.app')]
class Movements extends Component
{
    use WithPagination;

    #[Url(as: 'type')]
    public string $typeFilter = '';

    #[Url(as: 'q')]
    public string $search = '';

    public function updating(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $property = Property::query()->first();

        $movements = InventoryMovement::query()
            ->where('property_id', $property?->id)
            ->with(['product', 'fromLocation', 'toLocation', 'user', 'reservation'])
            ->when($this->typeFilter, fn ($q) => $q->where('type', $this->typeFilter))
            ->when($this->search, fn ($q) => $q->whereHas('product', fn ($p) =>
                $p->where('name', 'like', '%'.$this->search.'%')
                  ->orWhere('sku', 'like', '%'.$this->search.'%'),
            ))
            ->orderByDesc('occurred_at')
            ->paginate(30);

        return view('livewire.inventory.movements', [
            'movements' => $movements,
            'types'     => InventoryMovement::TYPES,
        ]);
    }
}
