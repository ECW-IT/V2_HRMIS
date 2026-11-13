<?php

namespace Modules\CoreHr\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\CoreHr\Models\Bank;
use Modules\CoreHr\Models\BankBranch;
use Modules\CoreHr\Models\EmployeeBankAccount;
use Modules\CoreHr\Models\Employment;

/**
 * @extends Factory<EmployeeBankAccount>
 */
class EmployeeBankAccountFactory extends Factory
{
    protected $model = EmployeeBankAccount::class;

    public function definition(): array
    {
        $bank = Bank::factory()->create();
        $branch = BankBranch::factory()->create(['bank_id' => $bank->id]);

        return [
            'employment_id' => Employment::factory(),
            'effective_from' => '2026-01-01',
            'effective_to' => null,
            'is_cash' => false,
            'bank_id' => $bank->id,
            'bank_branch_id' => $branch->id,
            'account_no' => fake()->numerify('##########'),
            'account_name' => fake()->name(),
        ];
    }
}
