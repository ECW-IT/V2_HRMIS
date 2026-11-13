<?php

namespace Modules\CoreHr\Livewire;

use App\Support\Company\CompanyContext;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Component;
use Livewire\WithPagination;
use Maatwebsite\Excel\Facades\Excel;
use Modules\CoreHr\Enums\EmploymentStatus;
use Modules\CoreHr\Enums\EmploymentType;
use Modules\CoreHr\Exceptions\InvalidNic;
use Modules\CoreHr\Exports\EmployeesExport;
use Modules\CoreHr\Models\Employment;
use Modules\CoreHr\Readiness\ReadinessService;
use Modules\CoreHr\Readiness\Severity;
use Modules\CoreHr\Support\EmployeesNav;
use Modules\CoreHr\Support\Nic;
use Modules\CoreHr\Support\OrgUnitOptions;
use Modules\Organisation\Models\Company;
use Modules\Organisation\Models\Grade;
use Modules\Organisation\Models\Job;
use Modules\Organisation\Models\OrgUnit;
use Modules\Organisation\Services\OrgUnitService;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class EmployeesIndex extends Component
{
    use WithPagination;

    private const SORTS = [
        'employee_no' => 'employments.employee_no',
        'name' => 'p.name_with_initials',
        'hire_date' => 'employments.hire_date',
    ];

    public string $search = '';

    public string|int|null $orgUnitId = null;

    public string|int|null $jobId = null;

    public string|int|null $gradeId = null;

    public string $type = '';

    /** ACTIVE, SUSPENDED, EXITED or ALL. */
    public string $status = 'ACTIVE';

    public string $sortBy = 'employee_no';

    public string $sortDir = 'asc';

    public function updated(string $name): void
    {
        if (! str_starts_with($name, 'paginators')) {
            $this->resetPage();
        }
    }

    public function sort(string $column): void
    {
        if (! array_key_exists($column, self::SORTS)) {
            return;
        }

        if ($this->sortBy === $column) {
            $this->sortDir = $this->sortDir === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $column;
            $this->sortDir = 'asc';
        }

        $this->resetPage();
    }

    public function export(): BinaryFileResponse
    {
        $this->authorize('employee.view');

        return Excel::download(new EmployeesExport($this->query()), 'employees.xlsx');
    }

    /** The filtered list query. Shared by the table and the export. */
    protected function query(): Builder
    {
        $today = CarbonImmutable::now(config('app.timezone'))->toDateString();
        // Current assignment; the last one for exited employees, the first one before the hire date.
        $ref = "LEAST(COALESCE(employments.exit_date, '9999-12-31'), GREATEST(?, employments.hire_date))";

        $query = Employment::query()
            ->select('employments.*')
            ->addSelect([
                'p.name_with_initials as name_with_initials',
                'p.nic as person_nic',
                'ou.name as department_name',
                'pou.name as parent_unit_name',
                'j.name as job_name',
                'g.name as grade_name',
            ])
            ->join('persons as p', 'p.id', '=', 'employments.person_id')
            ->leftJoin('assignments as a', function (JoinClause $join) use ($ref, $today) {
                $join->on('a.employment_id', '=', 'employments.id')
                    ->whereRaw("a.effective_from <= {$ref}", [$today])
                    ->whereRaw("(a.effective_to IS NULL OR a.effective_to >= {$ref})", [$today]);
            })
            ->leftJoin('org_units as ou', 'ou.id', '=', 'a.org_unit_id')
            ->leftJoin('org_units as pou', 'pou.id', '=', 'ou.parent_id')
            ->leftJoin('hr_jobs as j', 'j.id', '=', 'a.job_id')
            ->leftJoin('grades as g', 'g.id', '=', 'a.grade_id');

        $this->applySearch($query);

        if ($this->orgUnitId !== null && $this->orgUnitId !== '') {
            $unit = OrgUnit::query()->find((int) $this->orgUnitId);
            if ($unit === null) {
                $query->whereRaw('1 = 0');
            } else {
                $query->whereIn('a.org_unit_id', app(OrgUnitService::class)->descendantIds($unit));
            }
        }

        if ($this->jobId !== null && $this->jobId !== '') {
            $query->where('a.job_id', (int) $this->jobId);
        }

        if ($this->gradeId !== null && $this->gradeId !== '') {
            $query->where('a.grade_id', (int) $this->gradeId);
        }

        if (EmploymentType::tryFrom($this->type) !== null) {
            $query->where('employments.type', $this->type);
        }

        if (EmploymentStatus::tryFrom($this->status) !== null) {
            $query->where('employments.status', $this->status);
        }

        $column = self::SORTS[$this->sortBy] ?? self::SORTS['employee_no'];

        return $query
            ->orderBy($column, $this->sortDir === 'desc' ? 'desc' : 'asc')
            ->orderBy('employments.id');
    }

    public function render(): View
    {
        $this->authorize('employee.view');

        $employees = $this->query()->paginate(25);

        return view('corehr::livewire.employees-index', [
            'employees' => $employees,
            'blockerIds' => $this->blockerIds($employees->getCollection()),
            'orgUnits' => OrgUnitOptions::forCompany(CompanyContext::id()),
            'jobs' => Job::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'grades' => Grade::query()->where('is_active', true)->orderBy('rank')->get(['id', 'name']),
            'types' => EmploymentType::cases(),
            'statuses' => [
                'ACTIVE' => 'Active',
                'SUSPENDED' => 'Suspended',
                'EXITED' => 'Exited',
                'ALL' => 'All',
            ],
        ])->layout('components.layouts.module', [
            'moduleName' => 'Employees',
            'title' => 'Employees',
            'moduleNav' => EmployeesNav::items('employees'),
        ]);
    }

    private function applySearch(Builder $query): void
    {
        $term = trim($this->search);
        if ($term === '') {
            return;
        }

        $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term).'%';

        try {
            $nic = Nic::normalise($term);
        } catch (InvalidNic) {
            $nic = null;
        }

        $query->where(function (Builder $inner) use ($like, $nic) {
            $inner->where('employments.employee_no', 'like', $like)
                ->orWhere('p.name_with_initials', 'like', $like)
                ->orWhere('p.calling_name', 'like', $like);

            if ($nic !== null) {
                $inner->orWhere('p.nic', $nic);
            } else {
                $inner->orWhere('p.nic', 'like', $like);
            }
        });
    }

    /**
     * Employment ids on this page that have a payroll blocker today.
     *
     * @param  Collection<int, Employment>  $rows
     * @return list<int>
     */
    private function blockerIds($rows): array
    {
        $today = CarbonImmutable::now(config('app.timezone'))->toDateString();
        $readiness = app(ReadinessService::class);
        $ids = [];

        foreach ($rows->pluck('company_id')->unique() as $companyId) {
            $company = Company::query()->find($companyId);
            if ($company === null) {
                continue;
            }

            foreach ($readiness->run($company, $today) as $issue) {
                if ($issue->severity === Severity::BLOCKER) {
                    $ids[] = $issue->employmentId;
                }
            }
        }

        return array_values(array_unique($ids));
    }
}
