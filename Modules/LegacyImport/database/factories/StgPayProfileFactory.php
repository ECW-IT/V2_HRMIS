<?php

namespace Modules\LegacyImport\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\LegacyImport\Models\ImportRun;
use Modules\LegacyImport\Models\StgPayProfile;

/**
 * @extends Factory<StgPayProfile>
 */
class StgPayProfileFactory extends Factory
{
    protected $model = StgPayProfile::class;

    public function definition(): array
    {
        return [
            'import_run_id' => ImportRun::factory(),
            'legacy_profile_id' => fake()->unique()->numberBetween(1, 999999),
            'emp_no' => 'ECW '.fake()->unique()->numerify('###'),
            'company_code' => 'CO1',
            'company_id' => null,
            'employment_id' => null,
            'basic_salary' => '60000.00',
            'ot_rate_mode' => 'calculated',
            'attendance_type' => 'type1',
            'hostal' => 0,
            'branch_id' => 1,
            'status' => 1,
            'raw' => [],
            'blocked' => false,
            'loaded' => false,
            'load_error' => null,
        ];
    }
}
