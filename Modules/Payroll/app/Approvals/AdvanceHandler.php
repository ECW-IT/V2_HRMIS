<?php

namespace Modules\Payroll\Approvals;

use Modules\Payroll\Models\SalaryAdvance;
use Modules\Payroll\Services\AdvanceService;
use Modules\Workflow\Contracts\AppliesApproval;
use Modules\Workflow\Models\ApprovalRequest;

final class AdvanceHandler implements AppliesApproval
{
    public function __construct(private readonly AdvanceService $advances) {}

    public function apply(ApprovalRequest $request): void
    {
        $advance = SalaryAdvance::query()->findOrFail($request->subject_id);
        $this->advances->markApproved($advance, $request->id);
    }
}
