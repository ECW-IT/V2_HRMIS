<?php

namespace Modules\LegacyImport\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\LegacyImport\Models\ImportRun;
use Modules\LegacyImport\Models\StgPayComponent;
use Modules\LegacyImport\Models\StgPayProfile;

/**
 * @extends Factory<StgPayComponent>
 */
class StgPayComponentFactory extends Factory
{
    protected $model = StgPayComponent::class;

    public function definition(): array
    {
        return [
            'import_run_id' => ImportRun::factory(),
            'stg_pay_profile_id' => StgPayProfile::factory(),
            'legacy_adju_id' => fake()->unique()->numberBetween(1, 999999),
            'legacy_adjustment_type_id' => 1,
            'legacy_name' => 'Fuel',
            'v2_code' => 'FUEL',
            'action' => 'COMPONENT',
            'amount' => '2000.00',
            'skip_zero' => false,
            'blocked' => false,
        ];
    }
}
