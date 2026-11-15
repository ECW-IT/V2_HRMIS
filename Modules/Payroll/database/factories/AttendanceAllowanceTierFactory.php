<?php

namespace Modules\Payroll\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Payroll\Models\AttendanceAllowanceTier;

/**
 * @extends Factory<AttendanceAllowanceTier>
 */
class AttendanceAllowanceTierFactory extends Factory
{
    protected $model = AttendanceAllowanceTier::class;

    public function definition(): array
    {
        return [
            'code' => 'T1',
            'name' => 'Tier 1',
            'base_amount' => '5000.00',
            'per_day_rate' => '1000.00',
            'effective_from' => '2026-01-01',
            'effective_to' => null,
        ];
    }
}
