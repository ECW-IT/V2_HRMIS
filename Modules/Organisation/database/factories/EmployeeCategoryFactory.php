<?php

namespace Modules\Organisation\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Organisation\Models\EmployeeCategory;
use Modules\Organisation\Services\EmployeeCategoryService;

/**
 * @extends Factory<EmployeeCategory>
 */
class EmployeeCategoryFactory extends Factory
{
    protected $model = EmployeeCategory::class;

    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'code' => strtoupper(fake()->unique()->lexify('???')),
            'name' => ucwords($name),
            'name_key' => EmployeeCategoryService::nameKey($name),
            'is_active' => true,
            'annual_leaves' => 14,
            'casual_leaves' => 7,
            'medical_leaves' => 7,
            'normal_ot_rate' => '1.50',
            'double_ot_rate' => '2.00',
            'no_pay_rate_per_hour' => null,
            'no_pay_rate_per_day' => null,
            'saturday_rate' => null,
            'sunday_rate' => null,
            'payroll_workdays' => 26,
            'payroll_workhours' => 8,
            'saturday_ot_mode' => 1,
            'custom_saturday_ot_type' => null,
            'sunday_ot_mode' => 1,
            'custom_sunday_ot_type' => null,
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
