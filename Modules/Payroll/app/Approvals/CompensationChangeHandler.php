<?php

namespace Modules\Payroll\Approvals;

use Modules\CoreHr\Models\Employment;
use Modules\Payroll\Services\CompensationRequestService;
use Modules\Workflow\Contracts\AppliesApproval;
use Modules\Workflow\Models\ApprovalRequest;

final class CompensationChangeHandler implements AppliesApproval
{
    public function __construct(private readonly CompensationRequestService $requests) {}

    public function apply(ApprovalRequest $request): void
    {
        $employment = Employment::query()->findOrFail($request->subject_id);
        $payload = $request->payload;
        $operation = (string) $payload['operation'];

        $this->requests->apply($operation, $employment, $payload, $request->id);
    }
}
