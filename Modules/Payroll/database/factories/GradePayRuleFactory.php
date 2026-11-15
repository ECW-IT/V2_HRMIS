<?php

namespace Modules\Payroll\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Organisation\Models\Grade;
use Modules\Payroll\Enums\ExtraDayMethod;
use Modules\Payroll\Models\GradePayRule;

/**
 * @extends Factory<GradePayRule>
 */
class GradePayRuleFactory extends Factory
{
    protected $model = GradePayRule::class;

    public function definition(): array
    {
        return [
            'grade_id' => Grade::factory(),
            'extra_day_method' => ExtraDayMethod::HOURLY,
            'effective_from' => '2026-01-01',
            'effective_to' => null,
        ];
    }
}
