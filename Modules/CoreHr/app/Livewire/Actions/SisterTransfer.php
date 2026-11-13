<?php

namespace Modules\CoreHr\Livewire\Actions;

use App\Support\Company\CompanyContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Component;
use Modules\CoreHr\Livewire\Concerns\LoadsAssignmentOptions;
use Modules\CoreHr\Livewire\Concerns\SubmitsEmploymentRequests;
use Modules\CoreHr\Models\Employment;
use Modules\Organisation\Models\Company;

class SisterTransfer extends Component
{
    use LoadsAssignmentOptions;
    use SubmitsEmploymentRequests;

    public Employment $employment;

    public string|int|null $targetCompanyId = null;

    public string $lastWorkingDay = '';

    public string|int|null $orgUnitId = null;

    public string|int|null $jobId = null;

    public string|int|null $gradeId = null;

    public string|int|null $locationId = null;

    public string $epfNo = '';

    public function mount(Employment $employment): void
    {
        $this->employment = $employment;
        $this->lastWorkingDay = max(
            CarbonImmutable::now(config('app.timezone'))->toDateString(),
            $employment->hire_date->toDateString(),
        );
    }

    public function updatedTargetCompanyId(): void
    {
        // The units belong to the target company, so the old choice is no longer valid.
        $this->orgUnitId = null;
    }

    /** @return Collection<int, Company> */
    protected function targetCompanies(): Collection
    {
        return Company::query()
            ->whereIn('id', CompanyContext::permittedIds())
            ->where('id', '!=', $this->employment->company_id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    public function save(): void
    {
        $this->authorize('employee.exit');

        $targets = $this->targetCompanies()->pluck('id')->map(fn ($id) => (int) $id)->all();

        $this->validate([
            'targetCompanyId' => ['required', 'integer', 'in:'.implode(',', $targets ?: [0])],
            'lastWorkingDay' => ['required', 'date_format:Y-m-d'],
            'orgUnitId' => ['required', 'integer'],
            'jobId' => ['required', 'integer'],
            'gradeId' => ['required', 'integer'],
            'locationId' => ['required', 'integer'],
            'epfNo' => [$this->employment->epf_liable ? 'required' : 'nullable', 'string', 'max:20'],
        ], [
            'targetCompanyId.in' => 'Choose one of the companies in the list.',
            'targetCompanyId.required' => 'Choose a company.',
        ]);

        $target = (int) $this->targetCompanyId;

        $ok = $this->submitRequest('SISTER_TRANSFER', [
            'to_company_id' => $target,
            'last_day' => $this->lastWorkingDay,
            'assignment' => [
                'org_unit_id' => (int) $this->orgUnitId,
                'job_id' => (int) $this->jobId,
                'grade_id' => (int) $this->gradeId,
                'location_id' => (int) $this->locationId,
            ],
            'new_employment' => ['epf_no' => $this->epfNo !== '' ? $this->epfNo : null],
            'reason' => 'Transfer to sister company',
        ]);

        if (! $ok || ! $this->appliedAtOnce) {
            return;
        }

        $new = Employment::query()
            ->where('previous_employment_id', $this->employment->id)
            ->where('company_id', $target)
            ->latest('id')
            ->first();

        if ($new === null) {
            return;
        }

        session()->flash('success', "Transferred as {$new->employee_no}.");
        $this->redirect(route('employees.show', $new));
    }

    public function render(): View
    {
        $options = $this->assignmentOptions(
            $this->targetCompanyId !== null && $this->targetCompanyId !== '' ? (int) $this->targetCompanyId : null,
        );

        return view('corehr::livewire.actions.sister-transfer', [
            ...$options,
            'companies' => $this->targetCompanies(),
        ]);
    }
}
