<?php

namespace Modules\Leave\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Leave\Models\LeavePolicy;
use Modules\Leave\Models\LeaveType;

/**
 * @extends Factory<LeavePolicy>
 */
class LeavePolicyFactory extends Factory
{
    protected $model = LeavePolicy::class;

    public function definition(): array
    {
        return [
            'leave_type_id' => LeaveType::factory(),
            'company_id' => null,
            'grade_id' => null,
            'employment_type' => null,
            'days_per_period' => 8.0,
            'effective_from' => '2026-01-01',
            'effective_to' => null,
        ];
    }
}
