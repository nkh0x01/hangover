<?php

namespace App\Livewire\Inventory;

use App\Domain\Inventory\InventoryService;
use App\Models\Property;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Minibars')]
#[Layout('layouts.app')]
class Minibars extends Component
{
    public function render()
    {
        $property = Property::query()->first();
        $service = app(InventoryService::class);

        $rooms = $property?->rooms()->with([
            'minibarItems.product',
            'minibarLocation.stocks',
        ])->orderBy('number')->get() ?? collect();

        // For each room compute: total par, current stock, refill needed.
        $rows = $rooms->map(function ($room) {
            $byProduct = $room->minibarLocation?->stocks->keyBy('product_id') ?? collect();
            $totalPar = 0;
            $totalCurrent = 0;
            $needsRefill = 0;
            foreach ($room->minibarItems as $item) {
                $totalPar += $item->par_level;
                $current = (int) ($byProduct->get($item->product_id)->quantity ?? 0);
                $totalCurrent += $current;
                if ($current < $item->par_level) {
                    $needsRefill += $item->par_level - $current;
                }
            }
            return [
                'room' => $room,
                'par' => $totalPar,
                'current' => $totalCurrent,
                'needsRefill' => $needsRefill,
            ];
        });

        return view('livewire.inventory.minibars', compact('rows'));
    }
}
