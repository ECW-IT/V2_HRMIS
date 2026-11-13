<?php

namespace Modules\CoreHr\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\CoreHr\Enums\EmploymentStatus;
use Modules\CoreHr\Enums\EmploymentType;
use Modules\CoreHr\Models\Employment;
use Modules\CoreHr\Models\Person;
use Modules\Organisation\Models\Company;

/**
 * @extends Factory<Employment>
 */
class EmploymentFactory extends Factory
{
    protected $model = Employment::class;

    public function definition(): array
    {
        return [
            'person_id' => Person::factory(),
            'company_id' => Company::factory(),
            'employee_no' => strtoupper(fake()->unique()->bothify('EMP###')),
            'legacy_employee_no' => null,
            'type' => EmploymentType::PERMANENT,
            'status' => EmploymentStatus::ACTIVE,
            'hire_date' => '2026-01-01',
            'confirmation_due_date' => null,
            'confirmed_on' => null,
            'epf_liable' => true,
            'epf_no' => (string) fake()->unique()->numberBetween(100000, 999999),
            'exit_date' => null,
            'exit_reason' => null,
            'exit_remarks' => null,
            'previous_employment_id' => null,
        ];
    }
}
