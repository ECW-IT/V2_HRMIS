<?php

namespace Modules\Payroll\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Payroll\Models\LoanType;

/**
 * @extends Factory<LoanType>
 */
class LoanTypeFactory extends Factory
{
    protected $model = LoanType::class;

    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->lexify('????')),
            'name' => fake()->words(2, true),
            'is_active' => true,
        ];
    }
}
