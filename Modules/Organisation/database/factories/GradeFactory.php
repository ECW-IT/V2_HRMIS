<?php

namespace Modules\Organisation\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Organisation\Models\Grade;

/**
 * @extends Factory<Grade>
 */
class GradeFactory extends Factory
{
    protected $model = Grade::class;

    public function definition(): array
    {
        $rank = fake()->unique()->numberBetween(1, 65000);

        return [
            'code' => 'G'.$rank,
            'name' => 'Grade '.$rank,
            'rank' => $rank,
            'is_active' => true,
        ];
    }
}
