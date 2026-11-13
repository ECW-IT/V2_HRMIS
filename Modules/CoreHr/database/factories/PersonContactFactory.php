<?php

namespace Modules\CoreHr\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\CoreHr\Enums\ContactType;
use Modules\CoreHr\Models\Person;
use Modules\CoreHr\Models\PersonContact;

/**
 * @extends Factory<PersonContact>
 */
class PersonContactFactory extends Factory
{
    protected $model = PersonContact::class;

    public function definition(): array
    {
        return [
            'person_id' => Person::factory(),
            'type' => ContactType::MOBILE,
            'value' => '07'.fake()->numerify('########'),
            'is_primary' => false,
        ];
    }
}
