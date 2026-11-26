<?php

namespace Modules\CoreHr\Livewire\Tabs;

use App\Exceptions\Domain\DomainException;
use Carbon\CarbonImmutable;
use Illuminate\View\View;
use Livewire\Component;
use Modules\Attendance\Enums\AttendanceMode;
use Modules\Attendance\Enums\OtMode;
use Modules\Attendance\Services\TimeProfileService;
use Modules\CoreHr\Enums\EmploymentStatus;
use Modules\CoreHr\Enums\EmploymentType;
use Modules\CoreHr\Livewire\Concerns\LoadsAssignmentOptions;
use Modules\CoreHr\Livewire\Concerns\RejectsResignedMutations;
use Modules\CoreHr\Livewire\Concerns\SubmitsEmploymentRequests;
use Modules\CoreHr\Models\Employment;
use Modules\CoreHr\Support\AssignmentLookup;
use Modules\Organisation\Models\EmployeeCategory;
use Modules\Organisation\Models\StaffWorkCategory;

class EditEmploymentStep extends Component
{
    use LoadsAssignmentOptions;
    use RejectsResignedMutations;
    use SubmitsEmploymentRequests;

    public Employment $employment;

    public string $type = '';

    public string|int|null $employeeCategoryId = null;

    public string $classificationMessage = '';

    public string $effectiveFrom = '';

    public string|int|null $orgUnitId = null;

    public string|int|null $designationId = null;

    public string|int|null $locationId = null;

    public string $reason = '';

    public string|int|null $staffWorkCategoryId = null;

    public string $staffWorkMessage = '';

    public bool $hrmisActive = true;

    public bool $attendanceActive = true;

    public bool $payrollActive = true;

    public string $moduleFlagsMessage = '';

    public string $otMode = 'NONE';

    public string $otEffectiveFrom = '';

    public string $otModeMessage = '';

    public string $otModeError = '';

    public function mount(Employment $employment, TimeProfileService $profiles): void
    {
        $this->authorize('employee.update');
        $this->flashOnParent = false;
        $this->employment = $employment;
        $this->type = $employment->type->value;
        $this->employeeCategoryId = $employment->employee_category_id;
        $this->staffWorkCategoryId = $employment->staff_work_category_id;
        $this->hrmisActive = (bool) $employment->hrmis_active;
        $this->attendanceActive = (bool) $employment->attendance_active;
        $this->payrollActive = (bool) $employment->payroll_active;

        $today = CarbonImmutable::now(config('app.timezone'))->toDateString();
        $hire = $employment->hire_date->toDateString();
        $this->effectiveFrom = max($today, $hire);
        $this->otEffectiveFrom = $this->effectiveFrom;

        $profile = $profiles->on($employment, $today);
        $this->otMode = $profile?->ot_mode instanceof OtMode
            ? $profile->ot_mode->value
            : ($profile?->ot_mode !== null ? (string) $profile->ot_mode : OtMode::NONE->value);

        $this->loadAssignmentFields();
    }

    public function updatedEffectiveFrom(string $value): void
    {
        if ($value === '') {
            return;
        }

        $this->loadAssignmentFields($value);
    }

    private function loadAssignmentFields(?string $on = null): void
    {
        $date = $on ?? $this->effectiveFrom;
        $assignment = $date !== ''
            ? $this->employment->assignmentOn($date)
            : null;
        $assignment ??= AssignmentLookup::display($this->employment);

        $this->orgUnitId = $assignment?->org_unit_id;
        $this->designationId = $assignment?->designation_id;
        $this->locationId = $assignment?->location_id;
    }

    public function saveClassification(): void
    {
        $this->authorize('employee.update');
        $this->ensureNotResigned();
        $this->classificationMessage = '';

        $this->validate([
            'type' => ['required', 'string', 'in:'.implode(',', array_column(EmploymentType::cases(), 'value'))],
            'employeeCategoryId' => ['nullable', 'integer', 'exists:employee_categories,id'],
        ]);

        $categoryId = $this->employeeCategoryId === null || $this->employeeCategoryId === ''
            ? null
            : (int) $this->employeeCategoryId;

        if ($this->type === $this->employment->type->value && $categoryId === $this->employment->employee_category_id) {
            $this->classificationMessage = 'Nothing was changed.';

            return;
        }

        if ($this->submitRequest('UPDATE_CLASSIFICATION', [
            'classification' => [
                'type' => $this->type,
                'employee_category_id' => $categoryId,
            ],
        ])) {
            $this->classificationMessage = $this->message;
        }
    }

    public function saveAssignment(): void
    {
        $this->authorize('employee.update');
        $this->ensureNotResigned();

        $this->validate([
            'effectiveFrom' => ['required', 'date_format:Y-m-d', 'after_or_equal:'.$this->employment->hire_date->toDateString()],
            'orgUnitId' => ['required', 'integer'],
            'designationId' => ['required', 'integer'],
            'locationId' => ['required', 'integer'],
            'reason' => ['required', 'string', 'max:255'],
        ], [
            'effectiveFrom.after_or_equal' => 'The date cannot be before the hire date.',
        ]);

        $current = $this->employment->assignmentOn($this->effectiveFrom);
        $new = [
            'org_unit_id' => (int) $this->orgUnitId,
            'designation_id' => (int) $this->designationId,
            'grade_id' => $current?->grade_id !== null ? (int) $current->grade_id : null,
            'location_id' => (int) $this->locationId,
            'manager_employment_id' => $current?->manager_employment_id !== null
                ? (int) $current->manager_employment_id
                : null,
        ];

        $changes = [];
        foreach ($new as $field => $value) {
            $old = $current?->{$field};
            if (($old === null ? null : (int) $old) !== $value) {
                $changes[$field] = $value;
            }
        }

        if ($changes === []) {
            $this->message = '';
            $this->errorMessage = 'Nothing was changed.';

            return;
        }

        $this->submitRequest('CHANGE_ASSIGNMENT', [
            'from' => $this->effectiveFrom,
            'changes' => $changes,
            'reason' => $this->reason,
        ]);

        if ($this->appliedAtOnce) {
            $this->employment = $this->employment->fresh();
            $this->loadAssignmentFields($this->effectiveFrom);
        }
    }

    public function saveStaffWorkCategory(): void
    {
        $this->authorize('employee.update');
        $this->ensureNotResigned();

        $this->validate([
            'staffWorkCategoryId' => ['nullable', 'integer', 'exists:staff_work_categories,id'],
        ]);

        $categoryId = $this->staffWorkCategoryId === null || $this->staffWorkCategoryId === ''
            ? null
            : (int) $this->staffWorkCategoryId;

        if ($categoryId === $this->employment->staff_work_category_id) {
            $this->staffWorkMessage = 'Nothing was changed.';

            return;
        }

        if ($this->submitRequest('UPDATE_STAFF_CATEGORY', [
            'staff_work_category_id' => $categoryId,
        ])) {
            $this->staffWorkMessage = $this->message;
        }
    }

    public function updatedHrmisActive(bool $value): void
    {
        if ($value || $this->employment->isResigned()) {
            $this->hrmisActive = (bool) $this->employment->hrmis_active;

            return;
        }

        if ($this->employment->status === EmploymentStatus::EXITED || ! (bool) $this->employment->hrmis_active) {
            $this->hrmisActive = (bool) $this->employment->hrmis_active;

            return;
        }

        $this->authorize('employee.exit');
        $this->hrmisActive = true;
        $this->moduleFlagsMessage = 'Complete the exit form to deactivate HRMIS.';
        $this->dispatch('open-exit-action');
    }

    public function saveModuleFlags(): void
    {
        $this->authorize('employee.update');

        $this->validate([
            'hrmisActive' => ['boolean'],
            'attendanceActive' => ['boolean'],
            'payrollActive' => ['boolean'],
        ]);

        if ($this->employment->isResigned()) {
            $this->hrmisActive = (bool) $this->employment->hrmis_active;
            $this->attendanceActive = (bool) $this->employment->attendance_active;
            $this->submitModuleFlags(['payroll_active' => $this->payrollActive]);

            return;
        }

        $turningHrmisOff = $this->employment->status !== EmploymentStatus::EXITED
            && (bool) $this->employment->hrmis_active
            && ! $this->hrmisActive;

        if ($turningHrmisOff) {
            $this->authorize('employee.exit');
            $this->hrmisActive = true;
            $this->submitModuleFlags([
                'attendance_active' => $this->attendanceActive,
                'payroll_active' => $this->payrollActive,
            ]);
            $this->moduleFlagsMessage = 'Complete the exit form to deactivate HRMIS.';
            $this->dispatch('open-exit-action');

            return;
        }

        $this->submitModuleFlags([
            'hrmis_active' => $this->hrmisActive,
            'attendance_active' => $this->attendanceActive,
            'payroll_active' => $this->payrollActive,
        ]);
    }

    /** @param  array<string, bool>  $flags */
    private function submitModuleFlags(array $flags): void
    {
        $changed = [];
        foreach ($flags as $field => $value) {
            if ((bool) $this->employment->{$field} !== (bool) $value) {
                $changed[$field] = (bool) $value;
            }
        }

        if ($changed === []) {
            $this->moduleFlagsMessage = 'Nothing was changed.';

            return;
        }

        if ($this->submitRequest('UPDATE_MODULE_FLAGS', ['flags' => $changed])) {
            $this->moduleFlagsMessage = $this->message;
        }
    }

    public function saveOtMode(TimeProfileService $profiles): void
    {
        $this->authorize('employee.update');
        $this->ensureNotResigned();
        $this->otModeMessage = '';
        $this->otModeError = '';

        $this->validate([
            'otEffectiveFrom' => ['required', 'date_format:Y-m-d', 'after_or_equal:'.$this->employment->hire_date->toDateString()],
            'otMode' => ['required', 'string', 'in:'.implode(',', array_column(OtMode::cases(), 'value'))],
        ], [
            'otEffectiveFrom.after_or_equal' => 'The date cannot be before the hire date.',
        ]);

        $current = $profiles->on($this->employment, $this->otEffectiveFrom);

        if ($current === null) {
            $this->otModeError = 'No time profile is in force. Set a shift on the time profile before changing OT mode.';

            return;
        }

        $currentMode = $current->ot_mode instanceof OtMode
            ? $current->ot_mode->value
            : (string) $current->ot_mode;

        if ($currentMode === $this->otMode) {
            $this->otModeError = 'Nothing was changed.';

            return;
        }

        $requested = $this->otMode;

        try {
            $saved = $profiles->set($this->employment, [
                'effective_from' => $this->otEffectiveFrom,
                'shift_id' => $current->shift_id,
                'ot_mode' => $requested,
            ]);
        } catch (DomainException $e) {
            $this->otModeError = $e->getMessage();

            return;
        }

        $savedMode = $saved->ot_mode instanceof OtMode ? $saved->ot_mode->value : (string) $saved->ot_mode;
        $this->otMode = $savedMode;

        $attendance = $current->attendance_mode instanceof AttendanceMode
            ? $current->attendance_mode
            : AttendanceMode::from((string) $current->attendance_mode);

        if ($attendance === AttendanceMode::NO_PUNCH && $requested !== OtMode::NONE->value && $savedMode === OtMode::NONE->value) {
            $this->otModeMessage = 'No-punch attendance keeps OT mode at none.';

            return;
        }

        $this->otModeMessage = 'OT mode updated.';
    }

    public function render(): View
    {
        $this->authorize('employee.update');

        $employment = $this->employment->load(['employeeCategory', 'staffWorkCategory']);
        $today = CarbonImmutable::now(config('app.timezone'))->toDateString();

        return view('corehr::livewire.partials.edit-employment-step', [
            'employment' => $employment,
            'types' => EmploymentType::cases(),
            'otModes' => OtMode::cases(),
            'otProfile' => app(TimeProfileService::class)->on($employment, $today),
            'employeeCategories' => EmployeeCategory::query()
                ->where(function ($query) use ($employment) {
                    $query->where('is_active', true);
                    if ($employment->employee_category_id !== null) {
                        $query->orWhere('id', $employment->employee_category_id);
                    }
                })
                ->orderBy('name')
                ->get(['id', 'name']),
            'staffWorkCategories' => StaffWorkCategory::query()
                ->where(function ($query) use ($employment) {
                    $query->where('is_active', true);
                    if ($employment->staff_work_category_id !== null) {
                        $query->orWhere('id', $employment->staff_work_category_id);
                    }
                })
                ->orderBy('name')
                ->get(['id', 'code', 'name']),
            ...$this->assignmentOptions((int) $employment->company_id),
        ]);
    }
}
