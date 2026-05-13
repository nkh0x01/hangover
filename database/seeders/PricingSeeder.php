<?php

namespace Database\Seeders;

use App\Models\DailyRoomPrice;
use App\Models\PricingRule;
use App\Models\Property;
use Illuminate\Database\Seeder;

class PricingSeeder extends Seeder
{
    public function run(): void
    {
        $property = Property::query()->first();
        if (! $property) {
            return;
        }

        // 1. Weekend +15% (priority 100) — re-introduces Phase 1's hardcoded uplift as a real rule.
        PricingRule::firstOrCreate(
            ['property_id' => $property->id, 'type' => PricingRule::TYPE_WEEKEND, 'name' => 'Weekend uplift'],
            [
                'priority' => 100,
                'scope' => PricingRule::SCOPE_PROPERTY,
                'conditions' => ['days' => [5, 6]],
                'action' => ['type' => 'percent', 'value' => 15],
                'active' => true,
            ],
        );

        // 2. Summer season +25% (Jun–Sep)
        $year = now($property->timezone)->year;
        PricingRule::firstOrCreate(
            ['property_id' => $property->id, 'type' => PricingRule::TYPE_SEASONAL, 'name' => 'Summer season'],
            [
                'priority' => 200,
                'scope' => PricingRule::SCOPE_PROPERTY,
                'conditions' => [],
                'action' => ['type' => 'percent', 'value' => 25],
                'valid_from' => "{$year}-06-15",
                'valid_to'   => "{$year}-09-15",
                'active' => true,
            ],
        );

        // 3. Last-minute -10% within 3 days
        PricingRule::firstOrCreate(
            ['property_id' => $property->id, 'type' => PricingRule::TYPE_LAST_MINUTE, 'name' => 'Last-minute discount'],
            [
                'priority' => 300,
                'scope' => PricingRule::SCOPE_PROPERTY,
                'conditions' => ['max_days_to_arrival' => 3],
                'action' => ['type' => 'percent', 'value' => -10],
                'active' => true,
            ],
        );

        // 4. A sample manual override for tomorrow on every room type, plus
        //    a CTA day next week, so the calendar UI has data to show.
        $tomorrow  = now($property->timezone)->copy()->addDay()->toDateString();
        $weekOut   = now($property->timezone)->copy()->addDays(7)->toDateString();
        foreach ($property->roomTypes as $type) {
            DailyRoomPrice::firstOrCreate(
                ['room_type_id' => $type->id, 'room_id' => null, 'date' => $tomorrow],
                [
                    'property_id' => $property->id,
                    'price' => (float) $type->base_price + 30,
                    'min_stay' => 2,
                    'source' => DailyRoomPrice::SOURCE_MANUAL,
                ],
            );
            DailyRoomPrice::firstOrCreate(
                ['room_type_id' => $type->id, 'room_id' => null, 'date' => $weekOut],
                [
                    'property_id' => $property->id,
                    'closed_to_arrival' => true,
                    'source' => DailyRoomPrice::SOURCE_MANUAL,
                ],
            );
        }
    }
}
