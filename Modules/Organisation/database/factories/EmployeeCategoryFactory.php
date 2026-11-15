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
        ];
    }
}
