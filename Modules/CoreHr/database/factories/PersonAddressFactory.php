<?php

namespace Modules\CoreHr\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\CoreHr\Enums\AddressType;
use Modules\CoreHr\Models\Person;
use Modules\CoreHr\Models\PersonAddress;

/**
 * @extends Factory<PersonAddress>
 */
class PersonAddressFactory extends Factory
{
    protected $model = PersonAddress::class;

    public function definition(): array
    {
        return [
            'person_id' => Person::factory(),
            'type' => AddressType::PERMANENT,
            'line1' => fake()->streetAddress(),
            'line2' => null,
            'city' => fake()->city(),
            'district' => null,
            'postal_code' => null,
        ];
    }
}
