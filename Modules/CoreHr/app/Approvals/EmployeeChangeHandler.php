<?php

namespace Modules\CoreHr\Approvals;

use Modules\CoreHr\Models\Employment;
use Modules\CoreHr\Services\EmploymentRequestService;
use Modules\Workflow\Contracts\AppliesApproval;
use Modules\Workflow\Models\ApprovalRequest;

final class EmployeeChangeHandler implements AppliesApproval
{
    public function __construct(private readonly EmploymentRequestService $requests) {}

    public function apply(ApprovalRequest $request): void
    {
        $employment = Employment::query()->findOrFail($request->subject_id);
        $payload = $request->payload;
        $operation = (string) $payload['operation'];

        $this->requests->apply($operation, $employment, $payload, $request->id);
    }
}
