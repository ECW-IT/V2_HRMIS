<?php

namespace Modules\CoreHr\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\CoreHr\Models\District;
use Modules\CoreHr\Models\GnDivision;

/**
 * @extends Factory<GnDivision>
 */
class GnDivisionFactory extends Factory
{
    protected $model = GnDivision::class;

    public function definition(): array
    {
        return [
            'district_id' => District::factory(),
            'code' => strtoupper(fake()->unique()->bothify('GN####')),
            'name' => fake()->unique()->streetName().' GN',
            'latitude' => null,
            'longitude' => null,
        ];
    }
}
