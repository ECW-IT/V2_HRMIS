<?php

namespace Modules\CoreHr\Livewire\Tabs;

use Carbon\CarbonImmutable;
use Illuminate\View\View;
use Livewire\Component;
use Modules\CoreHr\Enums\ContactType;
use Modules\CoreHr\Enums\EmploymentStatus;
use Modules\CoreHr\Livewire\Concerns\RejectsResignedMutations;
use Modules\CoreHr\Models\Employment;
use Modules\CoreHr\Readiness\ReadinessService;
use Modules\CoreHr\Services\EmploymentService;
use Modules\CoreHr\Support\AssignmentLookup;
use Modules\Organisation\Models\StaffWorkCategory;
use Modules\Organisation\Services\StaffWorkCategoryService;

class Summary extends Component
{
    use RejectsResignedMutations;

    public Employment $employment;

    public string|int|null $staffWorkCategoryId = null;

    public string $staffWorkMessage = '';

    public bool $hrmisActive = true;

    public bool $attendanceActive = true;

    public bool $payrollActive = true;

    public string $moduleFlagsMessage = '';

    public function mount(Employment $employment): void
    {
        $this->employment = $employment;
        $this->staffWorkCategoryId = $employment->staff_work_category_id;
        $this->hrmisActive = (bool) $employment->hrmis_active;
        $this->attendanceActive = (bool) $employment->attendance_active;
        $this->payrollActive = (bool) $employment->payroll_active;
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
            $this->dispatch('employment-updated', message: $this->moduleFlagsMessage);

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
        $this->dispatch('employment-updated', message: $this->moduleFlagsMessage);
    }

    public function render(ReadinessService $readiness): View
    {
        $this->authorize('employee.view');

        $today = CarbonImmutable::now(config('app.timezone'))->toDateString();
        $employment = $this->employment->load(['person.contacts', 'employeeCategory', 'staffWorkCategory']);
        $person = $employment->person;

        $mobile = $person->contacts
            ->where('type', ContactType::MOBILE)
            ->sortByDesc('is_primary')
            ->first();

        return view('corehr::livewire.tabs.summary', [
            'employment' => $employment,
            'person' => $person,
            'assignment' => AssignmentLookup::display($employment),
            'mobile' => $mobile?->value,
            'age' => (int) CarbonImmutable::parse($person->date_of_birth->toDateString())->diffInYears(CarbonImmutable::parse($today)),
            'issues' => $readiness->forEmployment($employment, $today),
            // The company scope hides employments the user may not see.
            'others' => Employment::query()
                ->with('company')
                ->where('person_id', $employment->person_id)
                ->where('id', '!=', $employment->id)
                ->orderByDesc('hire_date')
                ->get(),
            'staffWorkCategories' => StaffWorkCategory::query()
                ->where(function ($query) use ($employment) {
                    $query->where('is_active', true);
                    if ($employment->staff_work_category_id !== null) {
                        $query->orWhere('id', $employment->staff_work_category_id);
                    }
                })
                ->orderBy('name')
                ->get(['id', 'code', 'name']),
        ]);
    }
}
