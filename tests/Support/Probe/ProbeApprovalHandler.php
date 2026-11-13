<?php

namespace Tests\Support\Probe;

use Modules\Workflow\Contracts\AppliesApproval;
use Modules\Workflow\Models\ApprovalRequest;

final class ProbeApprovalHandler implements AppliesApproval
{
    public static int $calls = 0;

    public static bool $shouldFail = false;

    public function apply(ApprovalRequest $request): void
    {
        self::$calls++;

        if (self::$shouldFail) {
            throw new \RuntimeException('boom');
        }

        $probe = ScopedProbe::query()->findOrFail($request->subject_id);
        $probe->update($request->payload);
    }
}
