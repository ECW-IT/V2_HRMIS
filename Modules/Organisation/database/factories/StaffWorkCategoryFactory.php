<?php

namespace Modules\Organisation\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Organisation\Models\StaffWorkCategory;

/**
 * @extends Factory<StaffWorkCategory>
 */
class StaffWorkCategoryFactory extends Factory
{
    protected $model = StaffWorkCategory::class;

    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->lexify('???')),
            'name' => ucwords(fake()->unique()->words(2, true)),
            'is_active' => true,
        ];
    }
}
