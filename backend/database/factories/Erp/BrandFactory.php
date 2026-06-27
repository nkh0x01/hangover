<?php

declare(strict_types=1);

namespace Database\Factories\Erp;

use App\Modules\Erp\Core\Models\Brand;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Brand>
 */
final class BrandFactory extends Factory
{
    protected $model = Brand::class;

    public function definition(): array
    {
        return [
            'code' => strtoupper($this->faker->unique()->lexify('BR????')),
            'name' => $this->faker->company(),
            'is_flagship' => false,
            'is_active' => true,
        ];
    }

    public function flagship(): self
    {
        return $this->state(['is_flagship' => true]);
    }
}
