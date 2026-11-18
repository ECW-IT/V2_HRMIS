<?php

namespace Modules\CoreHr\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\CoreHr\Models\Region;

/**
 * @extends Factory<Region>
 */
class RegionFactory extends Factory
{
    protected $model = Region::class;

    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->bothify('REG##')),
            'name' => fake()->unique()->word(),
            'is_active' => true,
            'sort_order' => 0,
        ];
    }
}
