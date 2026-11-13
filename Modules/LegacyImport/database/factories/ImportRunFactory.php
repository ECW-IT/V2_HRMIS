<?php

namespace Modules\LegacyImport\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\LegacyImport\Models\ImportRun;

/**
 * @extends Factory<ImportRun>
 */
class ImportRunFactory extends Factory
{
    protected $model = ImportRun::class;

    public function definition(): array
    {
        return [
            'started_at' => now(),
            'finished_at' => null,
            'stage' => 'STAGED',
            'summary' => null,
            'run_by' => null,
        ];
    }
}
