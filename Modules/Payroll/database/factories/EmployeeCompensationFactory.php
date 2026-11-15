<?php

namespace Modules\Payroll\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\CoreHr\Models\Employment;
use Modules\Payroll\Enums\OtRateMode;
use Modules\Payroll\Models\EmployeeCompensation;

/**
 * @extends Factory<EmployeeCompensation>
 */
class EmployeeCompensationFactory extends Factory
{
    protected $model = EmployeeCompensation::class;

    public function definition(): array
    {
        $employment = Employment::factory()->create();

        return [
            'company_id' => $employment->company_id,
            'employment_id' => $employment->id,
            'effective_from' => $employment->hire_date?->toDateString() ?? '2026-01-01',
            'effective_to' => null,
            'basic_salary' => '60000.00',
            'ot_rate_mode' => OtRateMode::CALCULATED,
            'ot_rate' => null,
            'attendance_tier_code' => null,
            'change_reason' => null,
            'approval_request_id' => null,
        ];
    }
}
