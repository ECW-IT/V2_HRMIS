<?php

namespace Modules\Payroll\Approvals;

use Modules\Payroll\Models\Loan;
use Modules\Payroll\Services\LoanService;
use Modules\Workflow\Contracts\AppliesApproval;
use Modules\Workflow\Models\ApprovalRequest;

final class LoanHandler implements AppliesApproval
{
    public function __construct(private readonly LoanService $loans) {}

    public function apply(ApprovalRequest $request): void
    {
        $loan = Loan::query()->findOrFail($request->subject_id);
        $this->loans->markApproved($loan, $request->id);
    }
}
