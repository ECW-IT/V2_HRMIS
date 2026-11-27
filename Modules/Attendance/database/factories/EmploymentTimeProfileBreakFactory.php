<?php

namespace Modules\Attendance\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Attendance\Models\EmploymentTimeProfile;
use Modules\Attendance\Models\EmploymentTimeProfileBreak;

/**
 * @extends Factory<EmploymentTimeProfileBreak>
 */
class EmploymentTimeProfileBreakFactory extends Factory
{
    protected $model = EmploymentTimeProfileBreak::class;

    public function definition(): array
    {
        return [
            'employment_time_profile_id' => EmploymentTimeProfile::factory(),
            'name' => 'Dinner',
            'start_time' => '20:00:00',
            'end_time' => '20:30:00',
            'is_paid' => false,
        ];
    }
}
