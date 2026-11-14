<?php

namespace Modules\CoreHr\Livewire;

use Carbon\CarbonImmutable;
use Illuminate\View\View;
use Livewire\Attributes\On;
use Livewire\Component;
use Modules\CoreHr\Enums\EmploymentStatus;
use Modules\CoreHr\Enums\EmploymentType;
use Modules\CoreHr\Models\Employment;
use Modules\CoreHr\Support\AssignmentLookup;
use Modules\CoreHr\Support\EmployeesNav;
use Modules\Workflow\Enums\ApprovalStatus;
use Modules\Workflow\Models\ApprovalRequest;

class EmployeeProfile extends Component
{
    public Employment $employment;

    public string $tab = 'summary';

    public ?string $action = null;

    public string $flash = '';

    public function mount(Employment $employment): void
    {
        $this->authorize('employee.view');
        $this->employment = $employment;

        $requested = request()->query('tab');
        if (is_string($requested) && array_key_exists($requested, $this->availableTabs())) {
            $this->tab = $requested;
        }
    }

    public function selectTab(string $tab): void
    {
        if (array_key_exists($tab, $this->availableTabs())) {
            $this->tab = $tab;
        }
    }

    public function openAction(string $action): void
    {
        abort_unless(array_key_exists($action, $this->availableActions()), 403);

        $this->flash = '';
        $this->action = $action;
    }

    #[On('close-action')]
    public function closeAction(): void
    {
        $this->action = null;
    }

    #[On('employment-updated')]
    public function afterAction(string $message = ''): void
    {
        $this->employment = $this->employment->fresh() ?? $this->employment;
        $this->action = null;
        $this->flash = $message;
    }

    /** @return array<string, string> tab key => label, only the tabs this user may open */
    protected function availableTabs(): array
    {
        $user = auth()->user();
        $tabs = [
            'summary' => 'Summary',
            'personal' => 'Personal',
            'history' => 'Employment and history',
        ];

        if ($user->can('employee.bank.view')) {
            $tabs['bank'] = 'Bank';
        }
        if ($user->can('employee.document.view')) {
            $tabs['documents'] = 'Documents';
        }
        if ($user->can('attendance.punch.view')) {
            $tabs['attendance'] = 'Attendance';
        }
        if ($user->can('leave.view')) {
            $tabs['leave'] = 'Leave';
        }

        $tabs['time'] = 'Time';
        $tabs['readiness'] = 'Readiness';

        if ($user->can('audit.view')) {
            $tabs['audit'] = 'Audit';
        }

        return $tabs;
    }

    /** @return array<string, string> action key => button label, for this user and this status */
    protected function availableActions(): array
    {
        $user = auth()->user();
        $status = $this->employment->status;
        $actions = [];

        if ($status !== EmploymentStatus::EXITED && $user->can('employee.update')) {
            $actions['change-assignment'] = 'Change assignment';
        }

        if ($status === EmploymentStatus::ACTIVE
            && $this->employment->type === EmploymentType::PROBATION
            && $user->can('employee.update')) {
            $actions['confirm'] = 'Confirm';
        }

        if ($user->can('employee.exit')) {
            if ($status === EmploymentStatus::ACTIVE) {
                $actions['suspend'] = 'Suspend';
                $actions['sister-transfer'] = 'Transfer to sister company';
            }
            if ($status === EmploymentStatus::SUSPENDED) {
                $actions['reinstate'] = 'Reinstate';
            }
            if ($status !== EmploymentStatus::EXITED) {
                $actions['exit'] = 'Exit';
            }
        }

        return $actions;
    }

    public function render(): View
    {
        $this->authorize('employee.view');

        $tabs = $this->availableTabs();
        if (! array_key_exists($this->tab, $tabs)) {
            $this->tab = 'summary';
        }

        $employment = $this->employment->load(['person', 'company']);

        $pending = ApprovalRequest::query()
            ->where('subject_type', Employment::class)
            ->where('subject_id', $employment->id)
            ->where('status', ApprovalStatus::PENDING)
            ->count();

        return view('corehr::livewire.employee-profile', [
            'employment' => $employment,
            'assignment' => AssignmentLookup::display($employment),
            'tabs' => $tabs,
            'actions' => $this->availableActions(),
            'pendingCount' => $pending,
            'service' => $this->lengthOfService($employment),
        ])->layout('components.layouts.module', [
            'moduleName' => 'Employees',
            'title' => $employment->employee_no,
            'moduleNav' => EmployeesNav::items('employees'),
        ]);
    }

    private function lengthOfService(Employment $employment): string
    {
        $from = CarbonImmutable::parse($employment->hire_date->toDateString());
        $to = $employment->exit_date !== null
            ? CarbonImmutable::parse($employment->exit_date->toDateString())
            : CarbonImmutable::now(config('app.timezone'))->startOfDay();

        if ($to->lessThan($from)) {
            return 'Starts '.$from->format('Y-m-d');
        }

        $years = $from->diffInYears($to);
        $months = $from->addYears((int) $years)->diffInMonths($to);
        $days = $from->addYears((int) $years)->addMonths((int) $months)->diffInDays($to);

        $parts = [];
        if ((int) $years > 0) {
            $parts[] = (int) $years.' '.((int) $years === 1 ? 'year' : 'years');
        }
        if ((int) $months > 0) {
            $parts[] = (int) $months.' '.((int) $months === 1 ? 'month' : 'months');
        }
        if ($parts === []) {
            $parts[] = (int) $days.' '.((int) $days === 1 ? 'day' : 'days');
        }

        return implode(' ', $parts);
    }
}
