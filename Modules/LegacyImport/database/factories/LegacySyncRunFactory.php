<?php

namespace Modules\LegacyImport\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\LegacyImport\Models\LegacySyncRun;
use Modules\Organisation\Models\Company;

/**
 * @extends Factory<LegacySyncRun>
 */
class LegacySyncRunFactory extends Factory
{
    protected $model = LegacySyncRun::class;

    public function definition(): array
    {
        $from = now()->startOfDay();

        return [
            'company_id' => Company::factory(),
            'from_at' => $from,
            'to_at' => now(),
            'file_path' => '',
            'new_hires' => 0,
            'exits' => 0,
            'changes' => 0,
            'applied_by' => null,
            'applied_at' => null,
            'created_at' => now(),
        ];
    }
}
