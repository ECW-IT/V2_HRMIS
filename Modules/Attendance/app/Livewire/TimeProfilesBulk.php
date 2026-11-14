<?php

namespace Modules\Attendance\Livewire;

use App\Support\Company\CompanyContext;
use Illuminate\View\View;
use Livewire\Component;
use Modules\Attendance\Enums\AttendanceMode;
use Modules\Attendance\Enums\OtMode;
use Modules\Attendance\Models\EmploymentTimeProfile;
use Modules\Attendance\Models\Shift;
use Modules\Attendance\Services\TimeProfileService;
use Modules\Attendance\Support\AttendanceNav;
use Modules\CoreHr\Enums\EmploymentStatus;
use Modules\CoreHr\Models\Employment;
use Modules\Organisation\Models\Grade;
use Modules\Organisation\Models\OrgUnit;

class TimeProfilesBulk extends Component
{
    public ?int $filterOrgUnitId = null;

    public ?int $filterGradeId = null;

    public ?int $filterShiftId = null;

    /** @var list<int> */
    public array $selected = [];

    public string $effective_from = '';

    public string|int|null $shift_id = null;

    public string $attendance_mode = 'PUNCH';

    public string $ot_mode = 'NONE';

    public bool $late_deduction = false;

    /** @var array<int, string> */
    public array $results = [];

    public function mount(): void
    {
        $this->authorize('employee.update');
        $this->effective_from = now(config('app.timezone'))->toDateString();
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
        $this->results = [];

        $validated = $this->validate([
            'selected' => ['required', 'array', 'min:1'],
            'effective_from' => ['required', 'date_format:Y-m-d'],
            'shift_id' => ['required', 'integer', 'exists:shifts,id'],
            'attendance_mode' => ['required'],
            'ot_mode' => ['required'],
            'late_deduction' => ['boolean'],
        ]);

        $raw = $service->setMany($validated['selected'], [
            'effective_from' => $validated['effective_from'],
            'shift_id' => (int) $validated['shift_id'],
            'attendance_mode' => $validated['attendance_mode'],
            'ot_mode' => $validated['ot_mode'],
            'late_deduction' => $validated['late_deduction'],
        ]);

        foreach ($raw as $employmentId => $result) {
            $this->results[$employmentId] = is_string($result)
                ? $result
                : 'Updated to '.$result->shift->code;
        }
    }

    public function render(): View
    {
        $this->authorize('employee.update');

        CompanyContext::ensureSoleSelected();
        $companyId = CompanyContext::id();
        $employees = collect();

        if ($companyId !== null) {
            $employees = Employment::query()
                ->with(['person', 'assignments' => fn ($q) => $q->current()])
                ->where('company_id', $companyId)
                ->whereIn('status', [EmploymentStatus::ACTIVE, EmploymentStatus::SUSPENDED])
                ->when($this->filterOrgUnitId, function ($q) {
                    $q->whereHas('assignments', fn ($a) => $a->current()->where('org_unit_id', $this->filterOrgUnitId));
                })
                ->when($this->filterGradeId, function ($q) {
                    $q->whereHas('assignments', fn ($a) => $a->current()->where('grade_id', $this->filterGradeId));
                })
                ->when($this->filterShiftId, function ($q) {
                    $q->whereHas('timeProfiles', fn ($p) => $p->current()->where('shift_id', $this->filterShiftId));
                })
                ->orderBy('employee_no')
                ->get();
        }

        return view('attendance::livewire.time-profiles-bulk', [
            'companyId' => $companyId,
            'employees' => $employees,
            'orgUnits' => $companyId ? OrgUnit::query()->where('company_id', $companyId)->orderBy('name')->get() : collect(),
            'grades' => Grade::query()->where('is_active', true)->orderBy('rank')->get(),
            'shifts' => Shift::query()->where('is_active', true)->orderBy('code')->get(),
            'attendanceModes' => AttendanceMode::cases(),
            'otModes' => OtMode::cases(),
            'currentShift' => function (Employment $e) {
                return EmploymentTimeProfile::query()->where('employment_id', $e->id)->current()->first()?->shift;
            },
        ])->layout('components.layouts.module', [
            'moduleName' => 'Attendance',
            'title' => 'Bulk time profiles',
            'moduleNav' => AttendanceNav::items('time-profiles'),
        ]);
    }
}
