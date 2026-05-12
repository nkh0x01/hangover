<?php

namespace Database\Seeders;

use App\Domain\Inventory\InventoryService;
use App\Models\InventoryLocation;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Property;
use App\Models\RoomMinibarItem;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class InventorySeeder extends Seeder
{
    public function run(): void
    {
        $property = Property::query()->orderBy('id')->first();
        if (! $property) {
            return;
        }

        $service = app(InventoryService::class);

        // -- Categories --
        $cats = [];
        foreach (['Drinks', 'Snacks', 'Spirits'] as $name) {
            $cats[$name] = ProductCategory::firstOrCreate(
                ['property_id' => $property->id, 'slug' => Str::slug($name)],
                ['name' => $name, 'sort_order' => 0],
            );
        }

        // -- Products --
        $catalogue = [
            ['name' => 'Coca-Cola 330ml',   'sku' => 'CC330',  'cost' => 1.50, 'sale' => 4.00, 'cat' => 'Drinks',  'par' => 2, 'init' => 60],
            ['name' => 'Sprite 330ml',      'sku' => 'SP330',  'cost' => 1.50, 'sale' => 4.00, 'cat' => 'Drinks',  'par' => 2, 'init' => 60],
            ['name' => 'Borjomi 500ml',     'sku' => 'BORJ500','cost' => 2.00, 'sale' => 5.00, 'cat' => 'Drinks',  'par' => 2, 'init' => 60],
            ['name' => 'Mineral Water 1L',  'sku' => 'WATER1L','cost' => 1.00, 'sale' => 3.00, 'cat' => 'Drinks',  'par' => 2, 'init' => 80],
            ['name' => 'Snickers',          'sku' => 'SNICK',  'cost' => 1.20, 'sale' => 3.50, 'cat' => 'Snacks',  'par' => 1, 'init' => 40],
            ['name' => 'Lays Classic',      'sku' => 'LAYS',   'cost' => 1.00, 'sale' => 3.00, 'cat' => 'Snacks',  'par' => 1, 'init' => 40],
            ['name' => 'Beer 500ml',        'sku' => 'BEER500','cost' => 2.50, 'sale' => 6.00, 'cat' => 'Drinks',  'par' => 2, 'init' => 50],
            ['name' => 'Wine 187ml',        'sku' => 'WINE187','cost' => 4.00, 'sale' => 12.00,'cat' => 'Spirits', 'par' => 1, 'init' => 20],
        ];

        $products = [];
        foreach ($catalogue as $row) {
            $products[$row['sku']] = Product::firstOrCreate(
                ['property_id' => $property->id, 'sku' => $row['sku']],
                [
                    'name' => $row['name'],
                    'category_id' => $cats[$row['cat']]->id,
                    'cost_price' => $row['cost'],
                    'sale_price' => $row['sale'],
                    'tax_rate' => 0,
                    'track_stock' => true,
                    'low_stock_threshold' => 10,
                    'active' => true,
                ],
            );
        }

        // -- Locations: 1 storage + 1 reception + 1 minibar per room --
        $storage = InventoryLocation::firstOrCreate(
            ['property_id' => $property->id, 'type' => InventoryLocation::TYPE_STORAGE, 'room_id' => null],
            ['name' => 'Storage', 'active' => true],
        );
        $reception = InventoryLocation::firstOrCreate(
            ['property_id' => $property->id, 'type' => InventoryLocation::TYPE_RECEPTION, 'room_id' => null],
            ['name' => 'Reception', 'active' => true],
        );

        foreach ($property->rooms as $room) {
            InventoryLocation::firstOrCreate(
                [
                    'property_id' => $property->id,
                    'type' => InventoryLocation::TYPE_ROOM_MINIBAR,
                    'room_id' => $room->id,
                ],
                ['name' => "Minibar — {$room->number}", 'active' => true],
            );

            // Par levels per minibar.
            foreach ($catalogue as $row) {
                RoomMinibarItem::firstOrCreate(
                    ['room_id' => $room->id, 'product_id' => $products[$row['sku']]->id],
                    ['par_level' => $row['par']],
                );
            }
        }

        // -- Initial stock at storage --
        foreach ($catalogue as $row) {
            $product = $products[$row['sku']];
            // Only seed once — if there's already stock, skip.
            if ($service->stockAt($product, $storage) > 0) {
                continue;
            }
            $service->receivePurchase(
                $product, $storage, $row['init'], $row['cost'], null,
                'Initial stock seed',
            );
        }

        // -- A small reception float so POS has something to sell out of the box.
        foreach (['CC330', 'SP330', 'WATER1L', 'SNICK', 'LAYS'] as $sku) {
            $product = $products[$sku];
            if ($service->stockAt($product, $reception) === 0) {
                $service->transfer(
                    $product, $storage, $reception, 10,
                    null, 'Initial reception float',
                );
            }
        }
    }
}
