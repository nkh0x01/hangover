<?php

namespace Database\Seeders;

use App\Models\Coupon;
use Illuminate\Database\Seeder;

class DemoCouponsSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            [
                'code' => 'WELCOME10',
                'discount_type' => 'percent',
                'amount' => 10,
                'min_amount' => 100,
                'expires_at' => now()->addMonths(1),
                'product_categories_json' => [],
                'description' => 'პირველი შენაძენი — 10% ფასდაკლება (მინ. 100 ლარი).',
            ],
            [
                'code' => 'CASE15',
                'discount_type' => 'percent',
                'amount' => 15,
                'product_categories_json' => [['id' => 1, 'name' => 'Cases', 'slug' => 'cases']],
                'expires_at' => now()->addWeeks(2),
                'description' => '15% ფასდაკლება ნებისმიერ ქეისზე.',
            ],
            [
                'code' => 'FREESHIP',
                'discount_type' => 'fixed_cart',
                'amount' => 0,
                'free_shipping' => true,
                'expires_at' => now()->addWeek(),
                'description' => 'უფასო კურიერი ამ კვირაში.',
            ],
        ];

        foreach ($rows as $r) {
            Coupon::updateOrCreate(['code' => $r['code']], array_merge($r, [
                'is_active' => true,
                'synced_at' => now(),
            ]));
        }
    }
}
