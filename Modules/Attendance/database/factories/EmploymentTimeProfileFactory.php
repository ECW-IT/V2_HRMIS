<?php

namespace Modules\Attendance\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Attendance\Enums\AttendanceMode;
use Modules\Attendance\Enums\OtMode;
use Modules\Attendance\Models\EmploymentTimeProfile;
use Modules\Attendance\Models\Shift;
use Modules\CoreHr\Models\Employment;

/**
 * @extends Factory<EmploymentTimeProfile>
 */
class EmploymentTimeProfileFactory extends Factory
{
    protected $model = EmploymentTimeProfile::class;

    public function definition(): array
    {
        return [
            'employment_id' => Employment::factory(),
            'effective_from' => '2026-01-01',
            'effective_to' => null,
            'shift_id' => Shift::factory(),
            'attendance_mode' => AttendanceMode::PUNCH,
            'ot_mode' => OtMode::NONE,
            'late_deduction' => false,
        ];
    }
}
