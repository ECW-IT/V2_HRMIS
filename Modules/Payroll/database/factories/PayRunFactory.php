<?php

namespace Modules\Payroll\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Calendar\Models\PayPeriod;
use Modules\Payroll\Enums\PayRunStatus;
use Modules\Payroll\Models\PayRun;

/**
 * @extends Factory<PayRun>
 */
class PayRunFactory extends Factory
{
    protected $model = PayRun::class;

    public function definition(): array
    {
        $period = PayPeriod::factory()->create();

        return [
            'company_id' => $period->company_id,
            'pay_period_id' => $period->id,
            'status' => PayRunStatus::DRAFT,
            'time_summary_version' => null,
            'employee_count' => 0,
            'error_count' => 0,
            'gross_sheet1' => '0.00',
            'gross_sheet2' => '0.00',
            'total_deductions' => '0.00',
            'net_pay' => '0.00',
            'employer_total' => '0.00',
            'carried_forward_out' => '0.00',
        ];
    }
}
