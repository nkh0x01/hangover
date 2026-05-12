<?php

namespace Database\Seeders;

use App\Models\Property;
use App\Models\Room;
use App\Models\RoomType;
use Illuminate\Database\Seeder;

class PropertySeeder extends Seeder
{
    public function run(): void
    {
        $property = Property::firstOrCreate(
            ['slug' => 'hotel-tbilisi'],
            [
                'name' => 'Hotel Tbilisi',
                'timezone' => 'Asia/Tbilisi',
                'base_currency' => 'GEL',
                'vat_rate_default' => 18,
                'address' => [
                    'line1' => 'Rustaveli Ave 1',
                    'city' => 'Tbilisi',
                    'country' => 'GE',
                ],
                'contact' => [
                    'email' => 'reception@example.test',
                    'phone' => '+995 32 000 0000',
                ],
                'settings' => [
                    'invoice_number_prefix' => 'HT',
                ],
                'active' => true,
            ],
        );

        $types = [
            ['name' => 'Standard', 'slug' => 'standard', 'base_price' => 120, 'capacity_adults' => 2, 'max_occupancy' => 2, 'bed_type' => 'queen'],
            ['name' => 'Deluxe',   'slug' => 'deluxe',   'base_price' => 180, 'capacity_adults' => 2, 'max_occupancy' => 3, 'bed_type' => 'king'],
            ['name' => 'Twin',     'slug' => 'twin',     'base_price' => 140, 'capacity_adults' => 2, 'max_occupancy' => 2, 'bed_type' => 'twin'],
            ['name' => 'Family',   'slug' => 'family',   'base_price' => 240, 'capacity_adults' => 2, 'capacity_children' => 2, 'max_occupancy' => 4, 'bed_type' => 'king'],
        ];

        $createdTypes = [];
        foreach ($types as $t) {
            $createdTypes[$t['slug']] = RoomType::firstOrCreate(
                ['property_id' => $property->id, 'slug' => $t['slug']],
                array_merge($t, ['property_id' => $property->id]),
            );
        }

        // Room layout: 12 rooms across 3 floors.
        // Floor 1: 101 std, 102 std, 103 twin, 104 deluxe
        // Floor 2: 201 std, 202 std, 203 twin, 204 deluxe
        // Floor 3: 301 deluxe, 302 family, 303 family, 304 std
        $layout = [
            ['number' => '101', 'floor' => 1, 'type' => 'standard'],
            ['number' => '102', 'floor' => 1, 'type' => 'standard'],
            ['number' => '103', 'floor' => 1, 'type' => 'twin'],
            ['number' => '104', 'floor' => 1, 'type' => 'deluxe'],
            ['number' => '201', 'floor' => 2, 'type' => 'standard'],
            ['number' => '202', 'floor' => 2, 'type' => 'standard'],
            ['number' => '203', 'floor' => 2, 'type' => 'twin'],
            ['number' => '204', 'floor' => 2, 'type' => 'deluxe'],
            ['number' => '301', 'floor' => 3, 'type' => 'deluxe'],
            ['number' => '302', 'floor' => 3, 'type' => 'family'],
            ['number' => '303', 'floor' => 3, 'type' => 'family'],
            ['number' => '304', 'floor' => 3, 'type' => 'standard'],
        ];

        foreach ($layout as $row) {
            Room::firstOrCreate(
                ['property_id' => $property->id, 'number' => $row['number']],
                [
                    'room_type_id' => $createdTypes[$row['type']]->id,
                    'floor' => $row['floor'],
                    'status' => Room::STATUS_AVAILABLE,
                ],
            );
        }
    }
}
