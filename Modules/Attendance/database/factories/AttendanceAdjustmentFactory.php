<?php

namespace Modules\Attendance\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Attendance\Enums\AdjustmentStatus;
use Modules\Attendance\Enums\AdjustmentType;
use Modules\Attendance\Models\AttendanceAdjustment;
use Modules\CoreHr\Models\Employment;

/**
 * @extends Factory<AttendanceAdjustment>
 */
class AttendanceAdjustmentFactory extends Factory
{
    protected $model = AttendanceAdjustment::class;

    public function definition(): array
    {
        $employment = Employment::factory()->create();

        return [
            'company_id' => $employment->company_id,
            'employment_id' => $employment->id,
            'work_date' => '2026-12-01',
            'type' => AdjustmentType::ADD_PUNCH,
            'punched_at' => '2026-12-01 17:05:00',
            'punch_id' => null,
            'reason' => 'Forgot to punch out',
            'status' => AdjustmentStatus::PENDING,
            'approval_request_id' => null,
            'requested_by' => User::factory(),
            'decided_by' => null,
            'decided_at' => null,
        ];
    }
}
