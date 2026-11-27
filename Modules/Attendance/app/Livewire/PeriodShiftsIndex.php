<?php

namespace Modules\Attendance\Livewire;

use App\Support\Company\CompanyContext;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Component;
use Modules\Attendance\Enums\OtMode;
use Modules\Attendance\Models\EmploymentTimeProfile;
use Modules\Attendance\Models\Shift;
use Modules\Attendance\Services\TimeProfileService;
use Modules\Attendance\Support\AttendanceNav;
use Modules\Calendar\Models\PayPeriod;
use Modules\Calendar\Services\PayPeriodService;
use Modules\CoreHr\Enums\EmploymentStatus;
use Modules\CoreHr\Models\Employment;
use Modules\Organisation\Enums\OrgUnitType;
use Modules\Organisation\Models\Company;
use Modules\Organisation\Models\Grade;
use Modules\Organisation\Models\OrgUnit;
use Modules\Organisation\Services\OrgUnitService;

class PeriodShiftsIndex extends Component
{
    public string|int|null $payPeriodId = null;

    public string $mode = 'departments';

    /** @var list<int|string> */
    public array $selectedDepartments = [];

    /** @var list<int> */
    public array $selected = [];

    public ?int $filterOrgUnitId = null;

    public ?int $filterGradeId = null;

    public ?int $filterShiftId = null;

    public string|int|null $shift_id = null;

    public string $ot_mode = 'NONE';

    public bool $late_deduction = false;

    public bool $breaks_apply = true;

    public string $start_time = '';

    public string $end_time = '';

    public string $late_from = '';

    public string $morning_cutoff = '';

    public string $half_day_from = '';

    public string $early_leave_from = '';

    /** @var list<array{name: string, start_time: string, end_time: string, is_paid: bool}> */
    public array $breaks = [];

    /** @var array<int, string> */
    public array $results = [];

    public string $formError = '';

    public function mount(): void
    {
        $this->authorize('employee.update');
        CompanyContext::ensureSoleSelected();
        $this->selectDefaultPeriod();
    }

    public function updatedShiftId(): void
    {
        $shift = Shift::query()->with('breaks')->find($this->shift_id);
        if ($shift === null) {
            return;
        }

        $this->start_time = substr((string) $shift->start_time, 0, 5);
        $this->end_time = substr((string) $shift->end_time, 0, 5);
        $this->late_from = substr((string) $shift->late_from, 0, 5);
        $this->morning_cutoff = substr((string) $shift->morning_cutoff, 0, 5);
        $this->half_day_from = substr((string) $shift->half_day_from, 0, 5);
        $this->early_leave_from = substr((string) $shift->early_leave_from, 0, 5);
        $this->breaks = $shift->breaks->map(fn ($break) => [
            'name' => $break->name,
            'start_time' => substr((string) $break->start_time, 0, 5),
            'end_time' => substr((string) $break->end_time, 0, 5),
            'is_paid' => (bool) $break->is_paid,
        ])->all();
    }

    public function addBreakRow(): void
    {
        $this->breaks[] = ['name' => '', 'start_time' => '20:00', 'end_time' => '20:30', 'is_paid' => false];
    }

    public function removeBreakRow(int $index): void
    {
        unset($this->breaks[$index]);
        $this->breaks = array_values($this->breaks);
    }

    public function toggle(int $employmentId): void
    {
        if (in_array($employmentId, $this->selected, true)) {
            $this->selected = array_values(array_filter($this->selected, fn ($id) => $id !== $employmentId));
        } else {
            $this->selected[] = $employmentId;
        }
    }

    public function apply(TimeProfileService $service): void
    {
        $this->authorize('employee.update');
        $this->formError = '';
        $this->results = [];

        $period = $this->period();
        if ($period === null) {
            $this->formError = 'Choose a pay month.';

            return;
        }

        $validated = $this->validate([
            'shift_id' => ['required', 'integer', 'exists:shifts,id'],
            'ot_mode' => ['required', 'in:NONE,MORNING,EVENING,BOTH'],
            'late_deduction' => ['boolean'],
            'breaks_apply' => ['boolean'],
            'start_time' => ['required'],
            'end_time' => ['required'],
            'late_from' => ['required'],
            'morning_cutoff' => ['required'],
            'half_day_from' => ['required'],
            'early_leave_from' => ['required'],
            'breaks' => ['array'],
            'breaks.*.name' => ['required', 'string', 'max:50'],
            'breaks.*.start_time' => ['required'],
            'breaks.*.end_time' => ['required'],
            'breaks.*.is_paid' => ['boolean'],
        ]);

        if ($this->mode === 'departments' && $this->selectedDepartments === []) {
            $this->formError = 'Select at least one department.';

            return;
        }

        if ($this->mode === 'employees' && $this->selected === []) {
            $this->formError = 'Tick at least one employee.';

            return;
        }

        $start = $period->start_date->toDateString();
        $ids = $this->targetEmploymentIds($start);

        if ($ids === []) {
            $this->formError = 'No employees in that selection on the first of this month.';

            return;
        }

        $raw = $service->setMany($ids, [
            'effective_from' => $start,
            'shift_id' => (int) $validated['shift_id'],
            'ot_mode' => $validated['ot_mode'],
            'late_deduction' => (bool) $validated['late_deduction'],
            'break_available' => $this->breaks_apply ? 'YES' : 'NO',
            'times' => [
                'start_time' => $validated['start_time'],
                'end_time' => $validated['end_time'],
                'late_from' => $validated['late_from'],
                'morning_cutoff' => $validated['morning_cutoff'],
                'half_day_from' => $validated['half_day_from'],
                'early_leave_from' => $validated['early_leave_from'],
            ],
            'breaks' => $validated['breaks'] ?? [],
        ]);

        foreach ($raw as $employmentId => $result) {
            $this->results[$employmentId] = is_string($result)
                ? $result
                : 'Updated to '.$result->shift->code.' from '.$start;
        }
    }

    public function render(): View
    {
        $this->authorize('employee.update');

        CompanyContext::ensureSoleSelected();
        $companyId = CompanyContext::id();
        $period = $this->period();
        $start = $period?->start_date->toDateString();
        $employees = $start !== null && $this->mode === 'employees'
            ? $this->listedEmployees($start)
            : collect();

        return view('attendance::livewire.period-shifts-index', [
            'companyId' => $companyId,
            'period' => $period,
            'periods' => $companyId
                ? PayPeriod::query()->where('company_id', $companyId)->orderByDesc('start_date')->get()
                : collect(),
            'departments' => $companyId
                ? OrgUnit::query()->where('company_id', $companyId)->where('type', OrgUnitType::DEPARTMENT)->where('is_active', true)->orderBy('name')->get()
                : collect(),
            'employees' => $employees,
            'orgUnits' => $companyId ? OrgUnit::query()->where('company_id', $companyId)->orderBy('name')->get() : collect(),
            'grades' => Grade::query()->where('is_active', true)->orderBy('rank')->get(),
            'shifts' => Shift::query()->where('is_active', true)->orderBy('code')->get(),
            'otModes' => OtMode::cases(),
            'currentShift' => function (Employment $e) use ($start) {
                if ($start === null) {
                    return null;
                }

                return EmploymentTimeProfile::query()->where('employment_id', $e->id)->effectiveOn($start)->first()?->shift;
            },
        ])->layout('components.layouts.module', [
            'moduleName' => 'Attendance',
            'title' => 'Pay month shifts',
            'moduleNav' => AttendanceNav::items('period-shifts'),
        ]);
    }

    private function selectDefaultPeriod(): void
    {
        $companyId = CompanyContext::id();
        if ($companyId === null) {
            return;
        }

        $company = Company::query()->find($companyId);
        if ($company === null) {
            return;
        }

        $now = Carbon::now(config('app.timezone'));
        $period = app(PayPeriodService::class)->ensure($company, (int) $now->year, (int) $now->month);
        $this->payPeriodId = $period->id;
    }

    private function period(): ?PayPeriod
    {
        if ($this->payPeriodId === null || $this->payPeriodId === '') {
            return null;
        }

        $companyId = CompanyContext::id();
        if ($companyId === null) {
            return null;
        }

        return PayPeriod::query()->where('company_id', $companyId)->find($this->payPeriodId);
    }

    /** @return list<int> */
    private function targetEmploymentIds(string $start): array
    {
        if ($this->mode === 'departments') {
            $orgIds = [];
            foreach ($this->selectedDepartments as $id) {
                $unit = OrgUnit::query()->find((int) $id);
                if ($unit === null) {
                    continue;
                }
                $orgIds = array_merge($orgIds, app(OrgUnitService::class)->descendantIds($unit));
            }

            $orgIds = array_values(array_unique(array_map('intval', $orgIds)));
            if ($orgIds === []) {
                return [];
            }

            return $this->employmentsOn($start, $orgIds)->pluck('id')->map(fn ($id) => (int) $id)->all();
        }

        $selected = array_map('intval', $this->selected);
        if ($selected === []) {
            return [];
        }

        return $this->employmentsOn($start)
            ->whereIn('id', $selected)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /** @param  list<int>|null  $orgIds */
    private function employmentsOn(string $start, ?array $orgIds = null): Collection
    {
        $companyId = CompanyContext::id();

        return Employment::query()
            ->with('person')
            ->where('company_id', $companyId)
            ->where('status', '!=', EmploymentStatus::EXITED)
            ->whereDate('hire_date', '<=', $start)
            ->whereHas('assignments', function ($query) use ($start, $orgIds) {
                $query->effectiveOn($start);
                if ($orgIds !== null) {
                    $query->whereIn('org_unit_id', $orgIds);
                }
            })
            ->orderBy('employee_no')
            ->get();
    }

    private function listedEmployees(string $start): Collection
    {
        return $this->employmentsOn($start)
            ->when($this->filterOrgUnitId, function (Collection $rows) use ($start) {
                return $rows->filter(function (Employment $employment) use ($start) {
                    return $employment->assignments()
                        ->effectiveOn($start)
                        ->where('org_unit_id', $this->filterOrgUnitId)
                        ->exists();
                });
            })
            ->when($this->filterGradeId, function (Collection $rows) use ($start) {
                return $rows->filter(function (Employment $employment) use ($start) {
                    return $employment->assignments()
                        ->effectiveOn($start)
                        ->where('grade_id', $this->filterGradeId)
                        ->exists();
                });
            })
            ->when($this->filterShiftId, function (Collection $rows) use ($start) {
                return $rows->filter(function (Employment $employment) use ($start) {
                    return EmploymentTimeProfile::query()
                        ->where('employment_id', $employment->id)
                        ->effectiveOn($start)
                        ->where('shift_id', $this->filterShiftId)
                        ->exists();
                });
            })
            ->values();
    }
}
