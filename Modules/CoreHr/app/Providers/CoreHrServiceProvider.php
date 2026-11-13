<?php

namespace Modules\CoreHr\Providers;

use Livewire\Livewire;
use Modules\CoreHr\Approvals\EmployeeChangeHandler;
use Modules\CoreHr\Approvals\EmployeeExitHandler;
use Modules\CoreHr\Livewire\Actions\ChangeAssignment;
use Modules\CoreHr\Livewire\Actions\ConfirmEmployment;
use Modules\CoreHr\Livewire\Actions\Enrol;
use Modules\CoreHr\Livewire\Actions\ExitEmployment;
use Modules\CoreHr\Livewire\Actions\Reinstate;
use Modules\CoreHr\Livewire\Actions\SisterTransfer;
use Modules\CoreHr\Livewire\Actions\Suspend;
use Modules\CoreHr\Livewire\BankBranchesIndex;
use Modules\CoreHr\Livewire\BanksIndex;
use Modules\CoreHr\Livewire\DocumentTypesIndex;
use Modules\CoreHr\Livewire\EmployeeProfile;
use Modules\CoreHr\Livewire\EmployeesIndex;
use Modules\CoreHr\Livewire\HireWizard;
use Modules\CoreHr\Livewire\ReadinessDashboard;
use Modules\CoreHr\Livewire\ReadinessHomeTile;
use Modules\CoreHr\Livewire\Tabs\Attendance;
use Modules\CoreHr\Livewire\Tabs\Audit;
use Modules\CoreHr\Livewire\Tabs\Bank;
use Modules\CoreHr\Livewire\Tabs\Documents;
use Modules\CoreHr\Livewire\Tabs\EmploymentHistory;
use Modules\CoreHr\Livewire\Tabs\Personal;
use Modules\CoreHr\Livewire\Tabs\Readiness;
use Modules\CoreHr\Livewire\Tabs\Summary;
use Modules\Workflow\Services\ApprovalService;
use Nwidart\Modules\Support\ModuleServiceProvider;

class CoreHrServiceProvider extends ModuleServiceProvider
{
    /**
     * The name of the module.
     */
    protected string $name = 'CoreHr';

    /**
     * The lowercase version of the module name.
     */
    protected string $nameLower = 'corehr';

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

        ApprovalService::registerHandler('EMPLOYEE_CHANGE', EmployeeChangeHandler::class);
        ApprovalService::registerHandler('EMPLOYEE_EXIT', EmployeeExitHandler::class);

        Livewire::component('corehr.readiness-dashboard', ReadinessDashboard::class);
        Livewire::component('corehr.readiness-home-tile', ReadinessHomeTile::class);
        Livewire::component('corehr.banks-index', BanksIndex::class);
        Livewire::component('corehr.bank-branches-index', BankBranchesIndex::class);
        Livewire::component('corehr.document-types-index', DocumentTypesIndex::class);
        Livewire::component('corehr.employees-index', EmployeesIndex::class);
        Livewire::component('corehr.hire-wizard', HireWizard::class);
        Livewire::component('corehr.employee-profile', EmployeeProfile::class);
        Livewire::component('corehr.tabs.summary', Summary::class);
        Livewire::component('corehr.tabs.personal', Personal::class);
        Livewire::component('corehr.tabs.employment-history', EmploymentHistory::class);
        Livewire::component('corehr.tabs.bank', Bank::class);
        Livewire::component('corehr.tabs.documents', Documents::class);
        Livewire::component('corehr.tabs.attendance', Attendance::class);
        Livewire::component('corehr.tabs.readiness', Readiness::class);
        Livewire::component('corehr.tabs.audit', Audit::class);
        Livewire::component('corehr.actions.change-assignment', ChangeAssignment::class);
        Livewire::component('corehr.actions.confirm-employment', ConfirmEmployment::class);
        Livewire::component('corehr.actions.suspend', Suspend::class);
        Livewire::component('corehr.actions.reinstate', Reinstate::class);
        Livewire::component('corehr.actions.exit-employment', ExitEmployment::class);
        Livewire::component('corehr.actions.sister-transfer', SisterTransfer::class);
        Livewire::component('corehr.actions.enrol', Enrol::class);
    }
}
