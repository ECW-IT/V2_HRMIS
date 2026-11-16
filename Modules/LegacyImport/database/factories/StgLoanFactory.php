<?php

namespace Modules\LegacyImport\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\LegacyImport\Models\ImportRun;
use Modules\LegacyImport\Models\StgLoan;
use Modules\LegacyImport\Models\StgPayProfile;

/**
 * @extends Factory<StgLoan>
 */
class StgLoanFactory extends Factory
{
    protected $model = StgLoan::class;

    public function definition(): array
    {
        return [
            'import_run_id' => ImportRun::factory(),
            'stg_pay_profile_id' => StgPayProfile::factory(),
            'legacy_loan_id' => fake()->unique()->numberBetween(1, 999999),
            'loan_type_id' => 1,
            'approved_amount' => '60000.00',
            'approved_installments' => 6,
            'if_approved' => 1,
            'status' => 1,
            'paid' => 1,
            'unpaid_schedule' => [],
            'outstanding' => '30000.00',
            'blocked' => false,
            'loaded' => false,
        ];
    }
}
