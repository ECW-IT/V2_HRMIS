<?php

namespace Modules\Leave\Livewire;

use Carbon\Carbon;
use Livewire\Component;
use Modules\CoreHr\Models\Employment;
use Modules\Leave\Models\LeavePolicy;
use Modules\Leave\Models\LeaveType;
use Modules\Leave\Services\LeavePolicyService;
use Modules\Organisation\Models\Company;
use Modules\Organisation\Models\Grade;

class LeavePoliciesIndex extends Component
{
    public ?int $filterTypeId = null;

    public ?int $leaveTypeId = null;

    public ?int $companyId = null;

    public ?int $gradeId = null;

    public string $employmentType = '';

    public string $daysPerPeriod = '8.0';

    public string $effectiveFrom = '';

    public ?int $whoEmploymentId = null;

    public string $whoDate = '';

    public ?string $whoResult = null;

    public function mount(): void
    {
        $this->authorize('leave.view');
        $this->effectiveFrom = Carbon::now(config('app.timezone'))->toDateString();
        $this->whoDate = $this->effectiveFrom;
    }

    public function save(): void
    {
        $this->authorize('leave.manage');

        $data = $this->validate([
            'leaveTypeId' => 'required|integer|exists:leave_types,id',
            'companyId' => 'nullable|integer|exists:companies,id',
            'gradeId' => 'nullable|integer|exists:grades,id',
            'employmentType' => 'nullable|string|max:12',
            'daysPerPeriod' => 'required|numeric|min:0',
            'effectiveFrom' => 'required|date',
        ]);

        LeavePolicy::query()->create([
            'leave_type_id' => $data['leaveTypeId'],
            'company_id' => $data['companyId'] ?: null,
            'grade_id' => $data['gradeId'] ?: null,
            'employment_type' => $data['employmentType'] !== '' ? $data['employmentType'] : null,
            'days_per_period' => $data['daysPerPeriod'],
            'effective_from' => $data['effectiveFrom'],
            'effective_to' => null,
        ]);

        $this->leaveTypeId = null;
        $this->companyId = null;
        $this->gradeId = null;
        $this->employmentType = '';
        $this->daysPerPeriod = '8.0';
    }

    public function end(int $id): void
    {
        $this->authorize('leave.manage');
        $policy = LeavePolicy::query()->findOrFail($id);
        $today = Carbon::now(config('app.timezone'))->toDateString();
        $from = Carbon::parse($policy->effective_from)->toDateString();
        $policy->update([
            'effective_to' => $from >= $today ? $today : Carbon::parse($today)->subDay()->toDateString(),
        ]);
    }

    public function whoGetsWhat(LeavePolicyService $policies): void
    {
        $this->authorize('leave.view');
        $this->validate([
            'whoEmploymentId' => 'required|integer|exists:employments,id',
            'whoDate' => 'required|date',
            'filterTypeId' => 'nullable|integer|exists:leave_types,id',
        ]);

        $employment = Employment::query()->findOrFail($this->whoEmploymentId);
        $typeId = $this->filterTypeId ?? LeaveType::query()->where('code', 'ML')->value('id');
        $policy = $policies->resolve($employment, (int) $typeId, $this->whoDate);

        if ($policy === null) {
            $this->whoResult = 'No policy applies.';

            return;
        }

        $why = [];
        $why[] = $policy->company_id ? 'company' : 'any company';
        $why[] = $policy->grade_id ? 'grade' : 'any grade';
        $why[] = $policy->employment_type ? 'employment type '.$policy->employment_type : 'any employment type';
        $this->whoResult = "{$policy->days_per_period} days/period (matched: ".implode(', ', $why).')';
    }

    public function render()
    {
        $query = LeavePolicy::query()->with(['leaveType', 'company', 'grade'])->orderByDesc('effective_from');
        if ($this->filterTypeId) {
            $query->where('leave_type_id', $this->filterTypeId);
        }

        return view('leave::livewire.leave-policies-index', [
            'policies' => $query->get(),
            'types' => LeaveType::query()->orderBy('code')->get(),
            'companies' => Company::query()->orderBy('name')->get(),
            'grades' => Grade::query()->orderBy('name')->get(),
        ])->layout('components.layouts.app');
    }
}
