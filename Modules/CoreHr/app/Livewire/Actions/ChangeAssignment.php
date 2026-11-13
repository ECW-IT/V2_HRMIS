<?php

namespace Modules\CoreHr\Livewire\Actions;

use Carbon\CarbonImmutable;
use Illuminate\View\View;
use Livewire\Component;
use Modules\CoreHr\Livewire\Concerns\LoadsAssignmentOptions;
use Modules\CoreHr\Livewire\Concerns\SearchesManagers;
use Modules\CoreHr\Livewire\Concerns\SubmitsEmploymentRequests;
use Modules\CoreHr\Models\Employment;
use Modules\CoreHr\Support\AssignmentLookup;

class ChangeAssignment extends Component
{
    use LoadsAssignmentOptions;
    use SearchesManagers;
    use SubmitsEmploymentRequests;

    public Employment $employment;

    public string $effectiveFrom = '';

    public string|int|null $orgUnitId = null;

    public string|int|null $jobId = null;

    public string|int|null $gradeId = null;

    public string|int|null $locationId = null;

    public string $reason = '';

    public function mount(Employment $employment): void
    {
        $this->employment = $employment;

        $today = CarbonImmutable::now(config('app.timezone'))->toDateString();
        $hire = $employment->hire_date->toDateString();
        $this->effectiveFrom = max($today, $hire);

        $current = AssignmentLookup::display($employment);
        $this->orgUnitId = $current?->org_unit_id;
        $this->jobId = $current?->job_id;
        $this->gradeId = $current?->grade_id;
        $this->locationId = $current?->location_id;
        $this->managerEmploymentId = $current?->manager_employment_id;
    }

    public function save(): void
    {
        $this->authorize('employee.update');

        $this->validate([
            'effectiveFrom' => ['required', 'date_format:Y-m-d', 'after_or_equal:'.$this->employment->hire_date->toDateString()],
            'orgUnitId' => ['required', 'integer'],
            'jobId' => ['required', 'integer'],
            'gradeId' => ['required', 'integer'],
            'locationId' => ['required', 'integer'],
            'reason' => ['required', 'string', 'max:255'],
        ], [
            'effectiveFrom.after_or_equal' => 'The date cannot be before the hire date.',
        ]);

        $current = $this->employment->assignmentOn($this->effectiveFrom);
        $new = [
            'org_unit_id' => (int) $this->orgUnitId,
            'job_id' => (int) $this->jobId,
            'grade_id' => (int) $this->gradeId,
            'location_id' => (int) $this->locationId,
            'manager_employment_id' => $this->managerEmploymentId === null || $this->managerEmploymentId === ''
                ? null
                : (int) $this->managerEmploymentId,
        ];

        // Only the fields that differ go into the request.
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
    }

    public function render(): View
    {
        return view('corehr::livewire.actions.change-assignment', [
            ...$this->assignmentOptions((int) $this->employment->company_id),
            'selectedManager' => $this->selectedManager(),
            'managerResults' => $this->managerResults((int) $this->employment->company_id, (int) $this->employment->id),
        ]);
    }
}
