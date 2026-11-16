<?php

namespace Modules\LegacyImport\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\LegacyImport\Models\ImportRun;
use Modules\LegacyImport\Models\StgAdvance;
use Modules\LegacyImport\Models\StgPayProfile;

/**
 * @extends Factory<StgAdvance>
 */
class StgAdvanceFactory extends Factory
{
    protected $model = StgAdvance::class;

    public function definition(): array
    {
        return [
            'import_run_id' => ImportRun::factory(),
            'stg_pay_profile_id' => StgPayProfile::factory(),
            'legacy_advance_id' => fake()->unique()->numberBetween(1, 999999),
            'amount' => '10000.00',
            'unpaid_amount' => '10000.00',
            'is_paid' => 1,
            'paid_date' => '2026-11-01',
            'status' => 1,
            'converted_to_loan_id' => null,
            'unpaid_deductions' => [],
            'blocked' => false,
            'loaded' => false,
        ];
    }
}
