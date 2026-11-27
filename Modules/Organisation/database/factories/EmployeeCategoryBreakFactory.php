<?php

namespace Modules\Organisation\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
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
            'name' => 'Lunch',
            'start_time' => '12:00:00',
            'end_time' => '12:30:00',
            'is_paid' => false,
        ];
    }
}
