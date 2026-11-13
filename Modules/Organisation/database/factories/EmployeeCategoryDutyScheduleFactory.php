<?php

namespace Modules\Organisation\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Organisation\Models\EmployeeCategory;
use Modules\Organisation\Models\EmployeeCategoryDutySchedule;

/**
 * @extends Factory<EmployeeCategoryDutySchedule>
 */
class EmployeeCategoryDutyScheduleFactory extends Factory
{
    protected $model = EmployeeCategoryDutySchedule::class;

    public function definition(): array
    {
        return [
            'employee_category_id' => EmployeeCategory::factory(),
            'effective_from' => '2000-01-01',
            'effective_to' => null,
            'on_duty_time' => '08:00:00',
            'off_duty_time' => '17:00:00',
            'morning_short_beginning' => null,
            'morning_short_ending' => null,
            'evening_short_beginning' => null,
            'evening_short_ending' => null,
            'halfday_beginning' => null,
            'late_on' => null,
        ];
    }
}
