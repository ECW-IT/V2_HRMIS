<?php

namespace Modules\CoreHr\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\CoreHr\Models\District;

/**
 * @extends Factory<District>
 */
class DistrictFactory extends Factory
{
    protected $model = District::class;

    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->bothify('D##')),
            'name' => fake()->unique()->city(),
            'latitude' => null,
            'longitude' => null,
        ];
    }
}
