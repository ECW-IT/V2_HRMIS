<?php

namespace Modules\Payroll\Jobs;

use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Modules\Payroll\Models\PayRun;
use Modules\Payroll\Services\PayRunService;

class CalculatePayRun implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $payRunId,
        public int $userId,
    ) {}

    public function handle(PayRunService $svc): void
    {
        $run = PayRun::query()->findOrFail($this->payRunId);
        $by = User::query()->findOrFail($this->userId);
        $svc->performCalculation($run, $by);
    }
}
