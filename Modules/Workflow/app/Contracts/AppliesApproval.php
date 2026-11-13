<?php

namespace Modules\Workflow\Contracts;

use Modules\Workflow\Models\ApprovalRequest;

interface AppliesApproval
{
    public function apply(ApprovalRequest $request): void;
}
