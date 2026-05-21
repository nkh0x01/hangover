<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Geo\Models\City;
use App\Modules\Pricing\Models\FareRule;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

final class FareRulesSeeder extends Seeder
{
    public function run(): void
    {
        $tbilisi = City::query()->where('slug', 'tbilisi')->first();
        if (! $tbilisi) {
            return;
        }

        foreach (['scooter_electric', 'scooter_petrol', 'moped'] as $vehicleType) {
            FareRule::query()->updateOrCreate(
                [
                    'city_id' => $tbilisi->id,
                    'vehicle_type' => $vehicleType,
                    'name' => 'default-'.$vehicleType,
                ],
                [
                    'base_fare' => 2.50,        // GEL
                    'price_per_km' => 1.20,
                    'price_per_min' => 0.15,
                    'minimum_fare' => 4.00,
                    'booking_fee' => 0.50,
                    'commission_rate' => 0.20,
                    'free_waiting_minutes' => 3,
                    'waiting_fee_per_min' => 0.20,
                    'cancellation_fee' => 2.00,
                    'active_from' => CarbonImmutable::parse('2024-01-01'),
                    'active_until' => null,
                    'day_of_week_mask' => 0x7F,
                    'starts_at_local' => '00:00:00',
                    'ends_at_local' => '23:59:59',
                ],
            );
        }
    }
}
