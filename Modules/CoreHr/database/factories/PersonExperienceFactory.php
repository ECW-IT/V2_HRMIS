<?php

namespace Modules\CoreHr\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\CoreHr\Models\Person;
use Modules\CoreHr\Models\PersonExperience;

/**
 * @extends Factory<PersonExperience>
 */
class PersonExperienceFactory extends Factory
{
    protected $model = PersonExperience::class;

    public function definition(): array
    {
        return [
            'person_id' => Person::factory(),
            'employer' => fake()->company(),
            'job_title' => fake()->jobTitle(),
            'from_date' => fake()->date(),
            'to_date' => null,
        ];
    }
}
