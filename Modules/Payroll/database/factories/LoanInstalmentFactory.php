<?php

namespace Modules\Payroll\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Payroll\Enums\InstalmentStatus;
use Modules\Payroll\Models\Loan;
use Modules\Payroll\Models\LoanInstalment;

/**
 * @extends Factory<LoanInstalment>
 */
class LoanInstalmentFactory extends Factory
{
    protected $model = LoanInstalment::class;

    public function definition(): array
    {
        return [
            'loan_id' => Loan::factory(),
            'sequence' => 1,
            'due_month' => '2027-01-01',
            'amount' => '10000.00',
            'status' => InstalmentStatus::DUE,
            'pay_run_id' => null,
            'note' => null,
        ];
    }
}
