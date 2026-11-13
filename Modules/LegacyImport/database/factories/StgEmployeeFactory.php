<?php

namespace Modules\LegacyImport\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\LegacyImport\Models\ImportRun;
use Modules\LegacyImport\Models\StgEmployee;

/**
 * @extends Factory<StgEmployee>
 */
class StgEmployeeFactory extends Factory
{
    protected $model = StgEmployee::class;

    public function definition(): array
    {
        return [
            'import_run_id' => ImportRun::factory(),
            'legacy_hrm_id' => fake()->unique()->numberBetween(1, 999999),
            'legacy_branch_id' => 1,
            'raw' => [],
            'normalised_employee_no' => 'ECW'.fake()->unique()->numerify('###'),
            'normalised_nic' => null,
            'company_code' => 'CO1',
            'loaded_employment_id' => null,
            'load_error' => null,
        ];
    }
}
