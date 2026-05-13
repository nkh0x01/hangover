<?php

namespace Database\Factories;

use App\Models\PricingRule;
use App\Models\Property;
use Illuminate\Database\Eloquent\Factories\Factory;

class PricingRuleFactory extends Factory
{
    public function definition(): array
    {
        return [
            'property_id' => Property::factory(),
            'name' => 'Weekend uplift',
            'type' => PricingRule::TYPE_WEEKEND,
            'priority' => 100,
            'scope' => PricingRule::SCOPE_PROPERTY,
            'conditions' => ['days' => [5, 6]],
            'action' => ['type' => 'percent', 'value' => 15],
            'active' => true,
        ];
    }
}
