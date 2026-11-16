<?php

namespace Modules\Payroll\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\CoreHr\Models\Employment;
use Modules\Organisation\Models\Grade;
use Modules\Payroll\Enums\PayRunEmployeeStatus;
use Modules\Payroll\Models\PayRun;
use Modules\Payroll\Models\PayRunEmployee;

/**
 * @extends Factory<PayRunEmployee>
 */
class PayRunEmployeeFactory extends Factory
{
    protected $model = PayRunEmployee::class;

    public function definition(): array
    {
        $run = PayRun::factory()->create();
        $employment = Employment::factory()->create(['company_id' => $run->company_id]);
        $grade = Grade::factory()->create();

        return [
            'pay_run_id' => $run->id,
            'company_id' => $run->company_id,
            'employment_id' => $employment->id,
            'status' => PayRunEmployeeStatus::OK,
            'error' => null,
            'employee_no' => $employment->employee_no ?? 'E001',
            'name' => 'Test Employee',
            'grade_id' => $grade->id,
            'org_unit_id' => null,
            'job_id' => null,
            'employment_type' => 'PERMANENT',
            'epf_liable' => true,
            'epf_no' => 'EPF001',
            'basic_salary' => '60000.00',
            'ot_rate' => '375.00',
            'extra_day_method' => 'HOURLY',
            'attendance_tier_code' => null,
            'expected_days' => '25.0',
            'credited_days' => '25.00',
            'extra_days' => '0.00',
            'nopay_days' => '0.00',
            'late_minutes_chargeable' => 0,
            'ot_minutes' => 0,
            'carried_forward_in' => '0.00',
            'is_cash' => true,
            'bank_id' => null,
            'bank_branch_id' => null,
            'account_no' => null,
            'account_name' => null,
            'gross_sheet1' => '0.00',
            'gross_sheet2' => '0.00',
            'total_deductions' => '0.00',
            'net_pay' => '0.00',
            'carried_forward_out' => '0.00',
            'epf_base' => '0.00',
            'taxable_pay' => '0.00',
            'employer_total' => '0.00',
        ];
    }
}
