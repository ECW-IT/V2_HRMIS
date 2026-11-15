<?php

namespace Modules\Payroll\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\CoreHr\Models\Employment;
use Modules\Payroll\Enums\LoanStatus;
use Modules\Payroll\Models\Loan;
use Modules\Payroll\Models\LoanType;

/**
 * @extends Factory<Loan>
 */
class LoanFactory extends Factory
{
    protected $model = Loan::class;

    public function definition(): array
    {
        $employment = Employment::factory()->create();
        $type = LoanType::query()->first() ?? LoanType::factory()->create(['code' => 'STAFF', 'name' => 'Staff loan']);

        return [
            'company_id' => $employment->company_id,
            'employment_id' => $employment->id,
            'loan_type_id' => $type->id,
            'loan_no' => 'LN-'.date('Y').'-'.fake()->unique()->numerify('####'),
            'amount' => '30000.00',
            'instalments' => 3,
            'first_month' => '2027-01-01',
            'reason' => null,
            'status' => LoanStatus::PENDING,
            'approval_request_id' => null,
            'paid_on' => null,
            'payment_reference' => null,
            'created_by' => User::factory(),
        ];
    }
}
