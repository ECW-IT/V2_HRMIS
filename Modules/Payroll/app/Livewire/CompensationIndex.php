<?php

namespace Modules\Payroll\Livewire;

use App\Exceptions\Domain\DomainException;
use App\Support\Company\CompanyContext;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\View\View;
use Livewire\Component;
use Modules\CoreHr\Models\Employment;
use Modules\Payroll\Enums\OtRateMode;
use Modules\Payroll\Models\AttendanceAllowanceTier;
use Modules\Payroll\Models\EmployeeCompensation;
use Modules\Payroll\Models\EmployeeComponent;
use Modules\Payroll\Models\PayComponent;
use Modules\Payroll\Services\CompensationRequestService;
use Modules\Payroll\Services\CompensationService;
use Modules\Payroll\Support\PayrollNav;

class CompensationIndex extends Component
{
    public string $employmentId = '';

    public bool $showCompensationForm = false;

    public bool $showComponentForm = false;

    public bool $editingComponent = false;

    public ?int $editingComponentId = null;

    public string $from = '';

    public string $basic_salary = '';

    public string $ot_rate_mode = 'CALCULATED';

    public string $ot_rate = '';

    public string $attendance_tier_code = '';

    public string $change_reason = '';

    public string $component_code = '';

    public string $component_amount = '';

    public string $component_from = '';

    public string $component_to = '';

    public string $component_note = '';

    public string $end_on = '';

    public ?string $formError = null;

    public ?string $notice = null;

    public function boot(): void
    {
        CompanyContext::ensureSoleSelected();
    }

    public function openCompensation(CompensationService $compensation): void
    {
        $this->authorize('compensation.manage');
        $this->formError = null;
        $this->resetCompensationFields();

        $employment = $this->selectedEmployment();
        if ($employment !== null) {
            $today = now(config('app.timezone'))->toDateString();
            $current = $compensation->on($employment, $today);

            if ($current !== null) {
                $this->basic_salary = (string) $current->basic_salary;
                $this->ot_rate_mode = $current->ot_rate_mode instanceof OtRateMode
                    ? $current->ot_rate_mode->value
                    : (string) $current->ot_rate_mode;
                $this->ot_rate = $current->ot_rate !== null ? (string) $current->ot_rate : '';
                $this->attendance_tier_code = $current->attendance_tier_code ?? '';
                $this->from = CarbonImmutable::now(config('app.timezone'))->startOfMonth()->addMonth()->toDateString();
            }
        }

        $this->showCompensationForm = true;
        $this->showComponentForm = false;
        $this->editingComponent = false;
        $this->editingComponentId = null;
    }

    public function openComponent(): void
    {
        $this->authorize('compensation.manage');
        $this->formError = null;
        $this->editingComponent = false;
        $this->editingComponentId = null;
        $this->component_code = '';
        $this->component_amount = '';
        $this->component_from = '';
        $this->component_to = '';
        $this->component_note = '';
        $this->showComponentForm = true;
        $this->showCompensationForm = false;
    }

    public function openEditComponent(int $componentId): void
    {
        $this->authorize('compensation.manage');
        $this->formError = null;

        $employment = $this->selectedEmployment();
        if ($employment === null) {
            $this->formError = 'Choose an employee.';

            return;
        }

        $row = EmployeeComponent::query()
            ->with('payComponent')
            ->where('employment_id', $employment->id)
            ->whereKey($componentId)
            ->firstOrFail();

        if ($row->effective_to !== null) {
            $this->formError = 'Only an open component can be changed.';

            return;
        }

        $this->editingComponent = true;
        $this->editingComponentId = $row->id;
        $this->component_code = (string) ($row->payComponent?->code ?? '');
        $this->component_amount = (string) $row->amount;
        $this->component_from = now(config('app.timezone'))->toDateString();
        $this->component_to = '';
        $this->component_note = (string) ($row->note ?? '');
        $this->showComponentForm = true;
        $this->showCompensationForm = false;
    }

    public function closeForm(): void
    {
        $this->showCompensationForm = false;
        $this->showComponentForm = false;
        $this->editingComponent = false;
        $this->editingComponentId = null;
        $this->formError = null;
    }

    public function saveCompensation(CompensationRequestService $requests): void
    {
        $this->authorize('compensation.manage');
        $this->formError = null;
        $this->notice = null;

        $this->validate([
            'employmentId' => ['required', 'integer'],
            'from' => ['required', 'date'],
            'basic_salary' => ['required', 'string'],
            'ot_rate_mode' => ['required', 'string'],
            'ot_rate' => ['nullable', 'string'],
            'attendance_tier_code' => ['nullable', 'string'],
            'change_reason' => ['nullable', 'string'],
        ]);

        $this->submit($requests, 'SET_COMPENSATION', [
            'from' => $this->from,
            'basic_salary' => $this->basic_salary,
            'ot_rate_mode' => $this->ot_rate_mode,
            'ot_rate' => $this->ot_rate !== '' ? $this->ot_rate : null,
            'attendance_tier_code' => $this->attendance_tier_code !== '' ? $this->attendance_tier_code : null,
            'change_reason' => $this->change_reason !== '' ? $this->change_reason : null,
        ]);
    }

    public function saveComponent(CompensationRequestService $requests): void
    {
        $this->authorize('compensation.manage');
        $this->formError = null;
        $this->notice = null;

        if ($this->editingComponent) {
            $this->validate([
                'employmentId' => ['required', 'integer'],
                'editingComponentId' => ['required', 'integer'],
                'component_amount' => ['required', 'string'],
                'component_from' => ['required', 'date'],
                'component_note' => ['nullable', 'string'],
            ]);

            $this->submit($requests, 'CHANGE_COMPONENT', [
                'employee_component_id' => $this->editingComponentId,
                'amount' => $this->component_amount,
                'from' => $this->component_from,
                'note' => $this->component_note !== '' ? $this->component_note : null,
            ]);

            return;
        }

        $this->validate([
            'employmentId' => ['required', 'integer'],
            'component_code' => ['required', 'string'],
            'component_amount' => ['required', 'string'],
            'component_from' => ['required', 'date'],
            'component_to' => ['nullable', 'date'],
            'component_note' => ['nullable', 'string'],
        ]);

        $this->submit($requests, 'ADD_COMPONENT', [
            'component_code' => $this->component_code,
            'amount' => $this->component_amount,
            'from' => $this->component_from,
            'to' => $this->component_to !== '' ? $this->component_to : null,
            'note' => $this->component_note !== '' ? $this->component_note : null,
        ]);
    }

    public function endComponent(int $componentId, CompensationRequestService $requests): void
    {
        $this->authorize('compensation.manage');
        $this->formError = null;
        $this->notice = null;

        $this->validate([
            'employmentId' => ['required', 'integer'],
            'end_on' => ['required', 'date'],
        ]);

        $this->submit($requests, 'END_COMPONENT', [
            'employee_component_id' => $componentId,
            'on' => $this->end_on,
        ]);
    }

    public function render(CompensationService $compensation): View
    {
        $this->authorize('compensation.view');

        $employment = $this->selectedEmployment();

        $today = now(config('app.timezone'))->toDateString();

        return view('payroll::livewire.compensation-index', [
            'employments' => Employment::query()->with('person')->orderBy('employee_no')->get(),
            'employment' => $employment,
            'current' => $employment !== null ? $compensation->on($employment, $today) : null,
            'history' => $employment !== null
                ? EmployeeCompensation::query()->where('employment_id', $employment->id)->orderByDesc('effective_from')->get()
                : collect(),
            'components' => $employment !== null
                ? EmployeeComponent::query()->where('employment_id', $employment->id)->with('payComponent')->orderByDesc('effective_from')->get()
                : collect(),
            'payComponents' => PayComponent::query()->where('is_active', true)->orderBy('code')->get(),
            'tiers' => AttendanceAllowanceTier::query()->orderBy('code')->pluck('code')->unique()->values(),
            'modes' => OtRateMode::cases(),
            'canManage' => auth()->user()?->can('compensation.manage') ?? false,
        ])->layout('components.layouts.module', [
            'moduleName' => 'Payroll',
            'title' => 'Compensation',
            'moduleNav' => PayrollNav::items('compensation'),
        ]);
    }

    private function selectedEmployment(): ?Employment
    {
        if ($this->employmentId === '') {
            return null;
        }

        return Employment::query()->with('person')->find((int) $this->employmentId);
    }

    private function resetCompensationFields(): void
    {
        $this->from = '';
        $this->basic_salary = '';
        $this->ot_rate_mode = OtRateMode::CALCULATED->value;
        $this->ot_rate = '';
        $this->attendance_tier_code = '';
        $this->change_reason = '';
    }

    /** @param  array<string, mixed>  $data */
    private function submit(CompensationRequestService $requests, string $operation, array $data): void
    {
        try {
            $approval = $requests->request(
                auth()->user(),
                $operation,
                Employment::query()->findOrFail((int) $this->employmentId),
                $data,
            );
        } catch (DomainException $e) {
            $this->formError = $e->getMessage();

            return;
        } catch (AuthorizationException $e) {
            $this->formError = $e->getMessage();

            return;
        }

        $this->notice = $approval === null ? 'Saved.' : 'Sent for approval.';
        $this->closeForm();
    }
}
