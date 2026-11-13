<?php

namespace Modules\Attendance\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Attendance\Models\BiometricEnrolment;
use Modules\CoreHr\Models\Employment;

/**
 * @extends Factory<BiometricEnrolment>
 */
class BiometricEnrolmentFactory extends Factory
{
    protected $model = BiometricEnrolment::class;

    public function definition(): array
    {
        return [
            'employment_id' => Employment::factory(),
            'device_group' => 'NIT',
            'device_user_id' => fake()->unique()->numberBetween(1, 99999),
            'valid_from' => '2026-01-01',
            'valid_to' => null,
        ];
    }
}
