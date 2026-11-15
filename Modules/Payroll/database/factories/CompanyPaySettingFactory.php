<?php

namespace Modules\Payroll\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Organisation\Models\Company;
use Modules\Payroll\Models\CompanyPaySetting;

/**
 * @extends Factory<CompanyPaySetting>
 */
class CompanyPaySettingFactory extends Factory
{
    protected $model = CompanyPaySetting::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'late_divisor' => 14400,
            'attendance_min_offset' => '2.0',
            'attendance_max_offset' => '3.0',
            'hours_per_month' => 240,
            'hours_per_day' => 8,
            'ot_multiplier' => '1.50',
            'effective_from' => '2026-01-01',
            'effective_to' => null,
        ];
    }
}
