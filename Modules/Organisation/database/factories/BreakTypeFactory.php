<?php

namespace Modules\Organisation\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Organisation\Models\BreakType;

/**
 * @extends Factory<BreakType>
 */
class BreakTypeFactory extends Factory
{
    protected $model = BreakType::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'is_paid' => false,
            'remarks' => null,
            'is_active' => true,
        ];
    }
}
