<?php

namespace Modules\Payroll\Providers;

use Illuminate\Console\Scheduling\Schedule;
use Livewire\Livewire;
use Modules\Payroll\Approvals\AdvanceHandler;
use Modules\Payroll\Approvals\CompensationChangeHandler;
use Modules\Payroll\Approvals\LoanHandler;
use Modules\Payroll\Enums\AdvanceStatus;
use Modules\Payroll\Enums\LoanStatus;
use Modules\Payroll\Livewire\AttendanceTiersIndex;
use Modules\Payroll\Livewire\CompanyPaySettingsIndex;
use Modules\Payroll\Livewire\CompensationIndex;
use Modules\Payroll\Livewire\GradePayRulesIndex;
use Modules\Payroll\Livewire\LoansIndex;
use Modules\Payroll\Livewire\LoanTypesIndex;
use Modules\Payroll\Livewire\PayComponentsIndex;
use Modules\Payroll\Livewire\PayEntriesIndex;
use Modules\Payroll\Livewire\SalaryAdvancesIndex;
use Modules\Payroll\Livewire\StatutoryRatesIndex;
use Modules\Payroll\Livewire\TaxSlabsIndex;
use Modules\Payroll\Livewire\YtdOpeningsIndex;
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

        Livewire::component('payroll.pay-components-index', PayComponentsIndex::class);
        Livewire::component('payroll.loan-types-index', LoanTypesIndex::class);
        Livewire::component('payroll.statutory-rates-index', StatutoryRatesIndex::class);
        Livewire::component('payroll.tax-slabs-index', TaxSlabsIndex::class);
        Livewire::component('payroll.attendance-tiers-index', AttendanceTiersIndex::class);
        Livewire::component('payroll.grade-pay-rules-index', GradePayRulesIndex::class);
        Livewire::component('payroll.company-pay-settings-index', CompanyPaySettingsIndex::class);
        Livewire::component('payroll.loans-index', LoansIndex::class);
        Livewire::component('payroll.salary-advances-index', SalaryAdvancesIndex::class);
        Livewire::component('payroll.pay-entries-index', PayEntriesIndex::class);
        Livewire::component('payroll.compensation-index', CompensationIndex::class);
        Livewire::component('payroll.ytd-openings-index', YtdOpeningsIndex::class);

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
