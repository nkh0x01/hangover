<?php

declare(strict_types=1);

namespace Database\Factories\Erp;

use App\Modules\Erp\Core\Models\Branch;
use App\Modules\Erp\Core\Models\Brand;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Branch>
 */
final class BranchFactory extends Factory
{
    protected $model = Branch::class;

    public function definition(): array
    {
        return [
            'code' => strtoupper($this->faker->unique()->lexify('GD????')),
            'name' => $this->faker->city(),
            'brand_id' => Brand::factory(),
            'city' => $this->faker->randomElement([
                'თბილისი', 'ბათუმი', 'რუსთავი', 'ქუთაისი', 'ზუგდიდი', 'თელავი',
            ]),
            'address' => $this->faker->streetAddress(),
            'rs_branch_code' => (string) $this->faker->numberBetween(1, 9999),
            'is_active' => true,
        ];
    }
}
