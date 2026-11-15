<?php

namespace Modules\Payroll\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Calendar\Models\PayPeriod;
use Modules\CoreHr\Models\Employment;
use Modules\Payroll\Enums\AdvanceStatus;
use Modules\Payroll\Models\SalaryAdvance;

/**
 * @extends Factory<SalaryAdvance>
 */
class SalaryAdvanceFactory extends Factory
{
    protected $model = SalaryAdvance::class;

    public function definition(): array
    {
        $employment = Employment::factory()->create();
        $period = PayPeriod::factory()->create(['company_id' => $employment->company_id]);

        return [
            'company_id' => $employment->company_id,
            'employment_id' => $employment->id,
            'pay_period_id' => $period->id,
            'amount' => '10000.00',
            'requested_on' => '2026-12-01',
            'reason' => null,
            'status' => AdvanceStatus::PENDING,
            'approval_request_id' => null,
            'paid_on' => null,
            'paid_by' => null,
            'payment_reference' => null,
            'recovered_pay_run_id' => null,
            'converted_loan_id' => null,
            'created_by' => User::factory(),
        ];
    }
}
