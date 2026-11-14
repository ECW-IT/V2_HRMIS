<?php

namespace Modules\Leave\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\CoreHr\Models\Employment;
use Modules\Leave\Enums\LeavePortion;
use Modules\Leave\Enums\LeaveStatus;
use Modules\Leave\Models\LeaveRequest;
use Modules\Leave\Models\LeaveType;

/**
 * @extends Factory<LeaveRequest>
 */
class LeaveRequestFactory extends Factory
{
    protected $model = LeaveRequest::class;

    public function definition(): array
    {
        $employment = Employment::factory()->create();

        return [
            'company_id' => $employment->company_id,
            'employment_id' => $employment->id,
            'leave_type_id' => LeaveType::factory(),
            'date' => '2026-12-15',
            'portion' => LeavePortion::FULL,
            'days' => 1.0,
            'reason' => null,
            'covering_employment_id' => null,
            'status' => LeaveStatus::PENDING,
            'approval_request_id' => null,
            'requested_by' => User::factory(),
            'decided_at' => null,
        ];
    }
}
