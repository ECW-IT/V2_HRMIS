<?php

namespace Modules\Attendance\Approvals;

use App\Models\User;
use Modules\Attendance\Models\AttendanceAdjustment;
use Modules\Attendance\Services\AdjustmentService;
use Modules\Workflow\Contracts\AppliesApproval;
use Modules\Workflow\Enums\Decision;
use Modules\Workflow\Models\ApprovalRequest;

final class AttendanceAdjustmentHandler implements AppliesApproval
{
    public function __construct(private readonly AdjustmentService $adjustments) {}

    public function apply(ApprovalRequest $request): void
    {
        $adjustment = AttendanceAdjustment::withoutGlobalScopes()->findOrFail($request->subject_id);

        $action = $request->actions()
            ->where('decision', Decision::APPROVED)
            ->orderByDesc('id')
            ->first();

        $by = $action !== null
            ? User::query()->findOrFail($action->user_id)
            : User::query()->findOrFail($request->requested_by);

        $this->adjustments->markApproved($adjustment, $by);
    }
}
