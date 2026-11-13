<?php

namespace Modules\CoreHr\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\CoreHr\Enums\Gender;
use Modules\CoreHr\Models\Person;

/**
 * @extends Factory<Person>
 */
class PersonFactory extends Factory
{
    protected $model = Person::class;

    public function definition(): array
    {
        return [
            'nic' => null,
            'nic_as_entered' => null,
            'passport_no' => strtoupper(fake()->unique()->bothify('N#######')),
            'title' => null,
            'first_name' => fake()->firstName(),
            'middle_name' => null,
            'last_name' => fake()->lastName(),
            'name_with_initials' => fake()->name(),
            'calling_name' => null,
            'date_of_birth' => fake()->dateTimeBetween('-50 years', '-20 years')->format('Y-m-d'),
            'gender' => fake()->randomElement([Gender::MALE, Gender::FEMALE]),
            'marital_status' => null,
            'nationality' => 'Sri Lankan',
            'photo_path' => null,
            'nic_mismatch' => false,
        ];
    }
}
