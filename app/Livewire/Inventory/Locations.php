<?php

namespace App\Livewire\Inventory;

use App\Models\InventoryLocation;
use App\Models\Property;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Inventory locations')]
#[Layout('layouts.app')]
class Locations extends Component
{
    public function render()
    {
        $property = Property::query()->first();

        $locations = InventoryLocation::query()
            ->where('property_id', $property?->id)
            ->with(['room', 'stocks.product'])
            ->withCount('stocks')
            ->orderBy('type')
            ->orderBy('name')
            ->get();

        return view('livewire.inventory.locations', compact('locations'));
    }
}
