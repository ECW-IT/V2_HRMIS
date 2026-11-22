<?php

namespace Modules\CoreHr\Livewire\Tabs;

use Carbon\CarbonImmutable;
use Illuminate\View\View;
use Livewire\Component;
use Modules\CoreHr\Enums\EmploymentStatus;
use Modules\CoreHr\Enums\EmploymentType;
use Modules\CoreHr\Livewire\Concerns\LoadsAssignmentOptions;
use Modules\CoreHr\Livewire\Concerns\RejectsResignedMutations;
use Modules\CoreHr\Livewire\Concerns\SubmitsEmploymentRequests;
use Modules\CoreHr\Models\Employment;
use Modules\CoreHr\Services\EmploymentService;
use Modules\CoreHr\Support\AssignmentLookup;
use Modules\Organisation\Models\EmployeeCategory;
use Modules\Organisation\Models\StaffWorkCategory;
use Modules\Organisation\Services\StaffWorkCategoryService;

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

    public function mount(Employment $employment): void
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

    public function saveClassification(EmploymentService $employments): void
    {
        $this->authorize('employee.update');
        $this->ensureNotResigned();
        $this->classificationMessage = '';

        $this->validate([
            'type' => ['required', 'string', 'in:'.implode(',', array_column(EmploymentType::cases(), 'value'))],
            'employeeCategoryId' => ['nullable', 'integer', 'exists:employee_categories,id'],
        ]);

        $this->employment = $employments->updateClassification($this->employment, [
            'type' => $this->type,
            'employee_category_id' => $this->employeeCategoryId === null || $this->employeeCategoryId === ''
                ? null
                : (int) $this->employeeCategoryId,
        ]);
        $this->type = $this->employment->type->value;
        $this->employeeCategoryId = $this->employment->employee_category_id;
        $this->classificationMessage = 'Type and employee category updated.';
        $this->dispatch('employment-updated', message: '');
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

    public function saveStaffWorkCategory(StaffWorkCategoryService $service): void
    {
        $this->authorize('employee.update');
        $this->ensureNotResigned();

        $this->validate([
            'staffWorkCategoryId' => ['nullable', 'integer', 'exists:staff_work_categories,id'],
        ]);

        $category = $this->staffWorkCategoryId === null || $this->staffWorkCategoryId === ''
            ? null
            : StaffWorkCategory::query()->findOrFail((int) $this->staffWorkCategoryId);

        $this->employment = $service->assign($this->employment, $category);
        $this->staffWorkCategoryId = $this->employment->staff_work_category_id;
        $this->staffWorkMessage = 'Office / shop category updated.';
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

    public function saveModuleFlags(EmploymentService $employments): void
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
            $this->employment = $employments->updateModuleFlags($this->employment, [
                'payroll_active' => $this->payrollActive,
            ]);
            $this->payrollActive = (bool) $this->employment->payroll_active;
            $this->moduleFlagsMessage = 'Module flags updated.';

            return;
        }

        $turningHrmisOff = $this->employment->status !== EmploymentStatus::EXITED
            && (bool) $this->employment->hrmis_active
            && ! $this->hrmisActive;

        if ($turningHrmisOff) {
            $this->authorize('employee.exit');
            $this->hrmisActive = true;
            $this->employment = $employments->updateModuleFlags($this->employment, [
                'attendance_active' => $this->attendanceActive,
                'payroll_active' => $this->payrollActive,
            ]);
            $this->attendanceActive = (bool) $this->employment->attendance_active;
            $this->payrollActive = (bool) $this->employment->payroll_active;
            $this->moduleFlagsMessage = 'Complete the exit form to deactivate HRMIS.';
            $this->dispatch('open-exit-action');

            return;
        }

        $this->employment = $employments->updateModuleFlags($this->employment, [
            'hrmis_active' => $this->hrmisActive,
            'attendance_active' => $this->attendanceActive,
            'payroll_active' => $this->payrollActive,
        ]);
        $this->hrmisActive = (bool) $this->employment->hrmis_active;
        $this->attendanceActive = (bool) $this->employment->attendance_active;
        $this->payrollActive = (bool) $this->employment->payroll_active;
        $this->moduleFlagsMessage = 'Module flags updated.';
    }

    public function render(): View
    {
        $this->authorize('employee.update');

        $employment = $this->employment->load(['employeeCategory', 'staffWorkCategory']);

        return view('corehr::livewire.partials.edit-employment-step', [
            'employment' => $employment,
            'types' => EmploymentType::cases(),
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
