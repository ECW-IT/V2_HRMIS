<?php

namespace Modules\Leave\Approvals;

use Modules\Leave\Models\LeaveRequest;
use Modules\Leave\Services\LeaveService;
use Modules\Workflow\Contracts\AppliesApproval;
use Modules\Workflow\Models\ApprovalRequest;

final class LeaveRequestHandler implements AppliesApproval
{
    public function __construct(private readonly LeaveService $leave) {}

    public function apply(ApprovalRequest $request): void
    {
        $leaveRequest = LeaveRequest::withoutGlobalScopes()->findOrFail($request->subject_id);
        $this->leave->markApproved($leaveRequest);
    }
}
