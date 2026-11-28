<?php

namespace Modules\Payroll\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Organisation\Models\EmployeeCategory;
use Modules\Payroll\Enums\ExtraDayMethod;
use Modules\Payroll\Models\CategoryPayRule;

/**
 * @extends Factory<CategoryPayRule>
 */
class CategoryPayRuleFactory extends Factory
{
    protected $model = CategoryPayRule::class;

    public function definition(): array
    {
        return [
            'employee_category_id' => EmployeeCategory::factory(),
            'extra_day_method' => ExtraDayMethod::HOURLY,
            'effective_from' => '2026-01-01',
            'effective_to' => null,
        ];
    }
}
