<?php

namespace Modules\CoreHr\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\CoreHr\Models\Person;
use Modules\CoreHr\Models\PersonEmergencyContact;

/**
 * @extends Factory<PersonEmergencyContact>
 */
class PersonEmergencyContactFactory extends Factory
{
    protected $model = PersonEmergencyContact::class;

    public function definition(): array
    {
        return [
            'person_id' => Person::factory(),
            'name' => fake()->name(),
            'relationship' => 'Spouse',
            'phone' => '07'.fake()->numerify('########'),
            'address' => null,
        ];
    }
}
