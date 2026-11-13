<?php

namespace Modules\Organisation\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Organisation\Models\Company;

/**
 * @extends Factory<Company>
 */
class CompanyFactory extends Factory
{
    protected $model = Company::class;

    public function definition(): array
    {
        $code = strtoupper(fake()->unique()->lexify('???'));

        return [
            'code' => $code,
            'name' => fake()->company(),
            'employee_no_prefix' => $code,
            'employee_no_padding' => 3,
            'next_employee_seq' => 1,
            'epf_employer_no' => null,
            'etf_employer_no' => null,
            'tin' => null,
            'is_active' => true,
        ];
    }
}
