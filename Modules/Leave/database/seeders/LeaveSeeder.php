<?php

namespace Modules\Leave\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Leave\Models\LeavePolicy;
use Modules\Leave\Models\LeaveType;

class LeaveSeeder extends Seeder
{
    public function run(): void
    {
        // PLACEHOLDER values — assumptions A1 and A2 in 06_phase_2_plan.md. Confirm with HR.
        $type = LeaveType::query()->updateOrCreate(
            ['code' => 'ML'],
            [
                'name' => 'Monthly Leave',
                'counts_as_credited' => false,
                'allows_half_day' => true,
                'enforce_balance' => true,
                'requires_document' => false,
                'is_active' => true,
            ],
        );

        LeavePolicy::query()->updateOrCreate(
            [
                'leave_type_id' => $type->id,
                'company_id' => null,
                'grade_id' => null,
                'employment_type' => null,
                'effective_from' => '2026-01-01',
            ],
            [
                'days_per_period' => 8.0,
                'effective_to' => null,
            ],
        );
    }
}
