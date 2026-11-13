<?php

namespace Modules\CoreHr\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\CoreHr\Models\Person;
use Modules\CoreHr\Models\PersonDependent;

/**
 * @extends Factory<PersonDependent>
 */
class PersonDependentFactory extends Factory
{
    protected $model = PersonDependent::class;

    public function definition(): array
    {
        return [
            'person_id' => Person::factory(),
            'name' => fake()->name(),
            'relationship' => 'Child',
            'date_of_birth' => fake()->optional()->date(),
        ];
    }
}
