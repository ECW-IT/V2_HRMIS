<?php

namespace Modules\LegacyImport\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\LegacyImport\Models\ImportRun;
use Modules\LegacyImport\Models\StgIssue;

/**
 * @extends Factory<StgIssue>
 */
class StgIssueFactory extends Factory
{
    protected $model = StgIssue::class;

    public function definition(): array
    {
        return [
            'import_run_id' => ImportRun::factory(),
            'legacy_hrm_id' => null,
            'code' => 'IMP001',
            'severity' => 'ERROR',
            'message' => 'Test issue',
        ];
    }
}
