<?php

namespace Modules\Organisation\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Organisation\Models\BreakType;
use Modules\Organisation\Models\EmployeeCategory;
use Modules\Organisation\Models\EmployeeCategoryBreak;

/**
 * @extends Factory<EmployeeCategoryBreak>
 */
class EmployeeCategoryBreakFactory extends Factory
{
    protected $model = EmployeeCategoryBreak::class;

    public function definition(): array
    {
        return [
            'employee_category_id' => EmployeeCategory::factory(),
            'break_type_id' => BreakType::factory(),
            'start_time' => '12:00:00',
            'end_time' => '12:30:00',
            'duration_minutes' => 30,
        ];
    }
}
