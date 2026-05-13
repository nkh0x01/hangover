<?php

namespace Database\Factories;

use App\Models\InventoryLocation;
use App\Models\Property;
use Illuminate\Database\Eloquent\Factories\Factory;

class InventoryLocationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'property_id' => Property::factory(),
            'type' => InventoryLocation::TYPE_STORAGE,
            'room_id' => null,
            'name' => 'Storage',
            'active' => true,
        ];
    }

    public function reception(): static
    {
        return $this->state(fn () => [
            'type' => InventoryLocation::TYPE_RECEPTION,
            'name' => 'Reception',
        ]);
    }

    public function storage(): static
    {
        return $this->state(fn () => [
            'type' => InventoryLocation::TYPE_STORAGE,
            'name' => 'Storage',
        ]);
    }
}
