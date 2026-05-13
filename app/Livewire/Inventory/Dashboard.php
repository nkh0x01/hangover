<?php

namespace App\Livewire\Inventory;

use App\Domain\Inventory\InventoryService;
use App\Models\InventoryLocation;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\Property;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Inventory')]
#[Layout('layouts.app')]
class Dashboard extends Component
{
    public function render()
    {
        $property = Property::query()->first();

        $totalProducts = Product::query()
            ->where('property_id', $property?->id)
            ->where('active', true)
            ->count();

        $totalLocations = InventoryLocation::query()
            ->where('property_id', $property?->id)
            ->where('active', true)
            ->count();

        $minibarCount = InventoryLocation::query()
            ->where('property_id', $property?->id)
            ->where('type', InventoryLocation::TYPE_ROOM_MINIBAR)
            ->count();

        $totalStockValue = (float) ProductStock::query()
            ->join('products', 'products.id', '=', 'product_stock.product_id')
            ->where('products.property_id', $property?->id)
            ->sum(\DB::raw('product_stock.quantity * products.cost_price'));

        $lowStock = app(InventoryService::class)->lowStockReport($property);

        $recentMovements = InventoryMovement::query()
            ->where('property_id', $property?->id)
            ->with(['product', 'fromLocation', 'toLocation', 'user'])
            ->orderByDesc('occurred_at')
            ->limit(8)
            ->get();

        return view('livewire.inventory.dashboard', [
            'property'        => $property,
            'totalProducts'   => $totalProducts,
            'totalLocations'  => $totalLocations,
            'minibarCount'    => $minibarCount,
            'totalStockValue' => $totalStockValue,
            'lowStock'        => $lowStock,
            'recentMovements' => $recentMovements,
        ]);
    }
}
