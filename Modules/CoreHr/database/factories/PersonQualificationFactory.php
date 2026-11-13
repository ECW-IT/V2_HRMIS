<?php

namespace Modules\CoreHr\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\CoreHr\Models\Person;
use Modules\CoreHr\Models\PersonQualification;

/**
 * @extends Factory<PersonQualification>
 */
class PersonQualificationFactory extends Factory
{
    protected $model = PersonQualification::class;

    public function definition(): array
    {
        return [
            'person_id' => Person::factory(),
            'type' => 'Degree',
            'institute' => fake()->company(),
            'title' => 'BSc',
            'year' => fake()->optional()->numberBetween(1990, 2024),
        ];
    }
}
