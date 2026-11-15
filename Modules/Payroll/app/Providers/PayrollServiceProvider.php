<?php

namespace Modules\Payroll\Providers;

use Illuminate\Console\Scheduling\Schedule;
use Modules\Payroll\Approvals\AdvanceHandler;
use Modules\Payroll\Approvals\CompensationChangeHandler;
use Modules\Payroll\Approvals\LoanHandler;
use Modules\Payroll\Enums\AdvanceStatus;
use Modules\Payroll\Enums\LoanStatus;
use Modules\Payroll\Models\Loan;
use Modules\Payroll\Models\SalaryAdvance;
use Modules\Payroll\Services\AdvanceService;
use Modules\Payroll\Services\LoanService;
use Modules\Workflow\Enums\ApprovalStatus;
use Modules\Workflow\Models\ApprovalRequest;
use Modules\Workflow\Services\ApprovalService;
use Nwidart\Modules\Support\ModuleServiceProvider;

class PayrollServiceProvider extends ModuleServiceProvider
{
    /**
     * The name of the module.
     */
    protected string $name = 'Payroll';

    /**
     * The lowercase version of the module name.
     */
    protected string $nameLower = 'payroll';

    /**
     * Provider classes to register.
     *
     * @var string[]
     */
    protected array $providers = [
        EventServiceProvider::class,
        RouteServiceProvider::class,
    ];

    public function boot(): void
    {
        parent::boot();

        ApprovalService::registerHandler('COMPENSATION_CHANGE', CompensationChangeHandler::class);
        ApprovalService::registerHandler('SALARY_ADVANCE', AdvanceHandler::class);
        ApprovalService::registerHandler('LOAN', LoanHandler::class);

        ApprovalRequest::updated(function (ApprovalRequest $request): void {
            if (! $request->wasChanged('status') || $request->status !== ApprovalStatus::REJECTED) {
                return;
            }

            if ($request->subject_type === SalaryAdvance::class) {
                $advance = SalaryAdvance::withoutGlobalScopes()->find($request->subject_id);
                if ($advance !== null && $advance->status === AdvanceStatus::PENDING) {
                    app(AdvanceService::class)->markRejected($advance);
                }
            }

            if ($request->subject_type === Loan::class) {
                $loan = Loan::withoutGlobalScopes()->find($request->subject_id);
                if ($loan !== null && $loan->status === LoanStatus::PENDING) {
                    app(LoanService::class)->markRejected($loan);
                }
            }
        });
    }

    /**
     * Define module schedules.
     *
     * @param  $schedule
     */
    // protected function configureSchedules(Schedule $schedule): void
    // {
    //     $schedule->command('inspire')->hourly();
    // }
}
