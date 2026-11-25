<?php

namespace Modules\CoreHr\Livewire;

use App\Support\Company\CompanyContext;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\View\View;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use Maatwebsite\Excel\Facades\Excel;
use Modules\CoreHr\Enums\EmploymentStatus;
use Modules\CoreHr\Enums\EmploymentType;
use Modules\CoreHr\Exceptions\InvalidNic;
use Modules\CoreHr\Exports\EmployeeHireTemplateExport;
use Modules\CoreHr\Exports\EmployeesExport;
use Modules\CoreHr\Imports\EmployeeHiresImport;
use Modules\CoreHr\Models\Employment;
use Modules\CoreHr\Services\EmployeeExcelHireService;
use Modules\CoreHr\Support\EmployeesNav;
use Modules\CoreHr\Support\Nic;
use Modules\CoreHr\Support\OrgUnitOptions;
use Modules\Organisation\Models\Company;
use Modules\Organisation\Models\Designation;
use Modules\Organisation\Models\EmployeeCategory;
use Modules\Organisation\Models\OrgUnit;
use Modules\Organisation\Services\OrgUnitService;
use Modules\Payroll\Exports\EmployeeComponentsTemplateExport;
use Modules\Payroll\Imports\EmployeeComponentsImport;
use Modules\Payroll\Services\EmployeeComponentExcelService;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class EmployeesIndex extends Component
{
    use WithFileUploads;
    use WithPagination;

    private const SORTS = [
        'employee_no' => 'employments.employee_no',
        'name' => 'p.name_with_initials',
        'hire_date' => 'employments.hire_date',
    ];

    public string $search = '';

    public string|int|null $orgUnitId = null;

    public string|int|null $designationId = null;

    public string|int|null $employeeCategoryId = null;

    public string $type = '';

    /** ACTIVE, SUSPENDED, EXITED or ALL. */
    public string $status = 'ACTIVE';

    /** '' = all, '1' = payroll active, '0' = payroll inactive. */
    public string $payrollActive = '';

    public string $sortBy = 'employee_no';

    public string $sortDir = 'asc';

    public ?int $editingEmploymentId = null;

    public int $editStep = 1;

    public bool $showHireUpload = false;

    /** @var mixed */
    public $hireExcelFile = null;

    public string $hireImportMessage = '';

    /** @var list<string> */
    public array $hireImportErrors = [];

    public bool $showPayrollUpload = false;

    /** @var mixed */
    public $payrollExcelFile = null;

    public string $payrollEffectiveFrom = '';

    public string $payrollImportMessage = '';

    /** @var list<string> */
    public array $payrollImportErrors = [];

    /** @var list<string> */
    public array $payrollImportWarnings = [];

    public function openEdit(int $employmentId): void
    {
        $this->authorize('employee.update');

        $employment = Employment::query()->findOrFail($employmentId);
        abort_if($employment->isResigned(), 403);

        $this->editingEmploymentId = $employment->id;
        $this->editStep = 1;
    }

    public function closeEdit(): void
    {
        $this->editingEmploymentId = null;
        $this->editStep = 1;
    }

    public function setEditStep(int $step): void
    {
        if ($step < 1 || $step > 3) {
            return;
        }

        $this->editStep = $step;
    }

    #[On('employment-updated')]
    public function refreshList(string $message = ''): void {}

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

    public function downloadHireTemplate(): BinaryFileResponse
    {
        $this->authorize('employee.create');

        if (CompanyContext::id() === null) {
            abort(403, 'Select a company in the header first.');
        }

        return Excel::download(
            new EmployeeHireTemplateExport,
            'employee_hire_template.xlsx'
        );
    }

    public function openHireUpload(): void
    {
        $this->authorize('employee.create');
        $this->showHireUpload = true;
        $this->hireExcelFile = null;
        $this->hireImportMessage = '';
        $this->hireImportErrors = [];
    }

    public function closeHireUpload(): void
    {
        $this->showHireUpload = false;
        $this->hireExcelFile = null;
        $this->hireImportMessage = '';
        $this->hireImportErrors = [];
    }

    public function uploadHires(EmployeeExcelHireService $hireService): void
    {
        $this->authorize('employee.create');

        $companyId = CompanyContext::id();
        if ($companyId === null) {
            $this->hireImportErrors = ['Select a company in the header first.'];
            $this->hireImportMessage = '';

            return;
        }

        $this->validate([
            'hireExcelFile' => ['required', 'file', 'mimes:xlsx,xls', 'max:10240'],
        ]);

        $import = new EmployeeHiresImport;
        Excel::import($import, $this->hireExcelFile->getRealPath());

        if ($import->rows === []) {
            $this->hireImportErrors = ['No data rows found in the Excel file.'];
            $this->hireImportMessage = '';

            return;
        }

        $company = Company::query()->findOrFail($companyId);
        $result = $hireService->process($company, $import->rows);

        $this->hireImportErrors = $result['errors'];
        $this->hireImportMessage = 'Upload completed. Success: '.$result['success_count']
            .', Errors: '.$result['error_count'].'.';

        if ($result['hired'] !== []) {
            $this->hireImportMessage .= ' Hired: '.implode(', ', $result['hired']).'.';
        }

        $this->hireExcelFile = null;
        $this->resetPage();
    }

    public function downloadPayrollTemplate(): BinaryFileResponse
    {
        $this->authorize('compensation.manage');

        if (CompanyContext::id() === null) {
            abort(403, 'Select a company in the header first.');
        }

        return Excel::download(
            new EmployeeComponentsTemplateExport,
            'employee_payroll_components_template.xlsx'
        );
    }

    public function openPayrollUpload(): void
    {
        $this->authorize('compensation.manage');
        $this->showPayrollUpload = true;
        $this->payrollExcelFile = null;
        $this->payrollEffectiveFrom = CarbonImmutable::now(config('app.timezone'))->startOfMonth()->toDateString();
        $this->payrollImportMessage = '';
        $this->payrollImportErrors = [];
        $this->payrollImportWarnings = [];
    }

    public function closePayrollUpload(): void
    {
        $this->showPayrollUpload = false;
        $this->payrollExcelFile = null;
        $this->payrollImportMessage = '';
        $this->payrollImportErrors = [];
        $this->payrollImportWarnings = [];
    }

    public function uploadPayrollComponents(EmployeeComponentExcelService $payrollService): void
    {
        $this->authorize('compensation.manage');

        $companyId = CompanyContext::id();
        if ($companyId === null) {
            $this->payrollImportErrors = ['Select a company in the header first.'];
            $this->payrollImportWarnings = [];
            $this->payrollImportMessage = '';

            return;
        }

        $this->validate([
            'payrollExcelFile' => ['required', 'file', 'mimes:xlsx,xls', 'max:10240'],
            'payrollEffectiveFrom' => ['required', 'date'],
        ]);

        $import = new EmployeeComponentsImport;
        Excel::import($import, $this->payrollExcelFile->getRealPath());

        if ($import->rows === []) {
            $this->payrollImportErrors = ['No data rows found in the Excel file.'];
            $this->payrollImportWarnings = [];
            $this->payrollImportMessage = '';

            return;
        }

        $company = Company::query()->findOrFail($companyId);
        $result = $payrollService->process($company, $import->rows, $this->payrollEffectiveFrom);

        $this->payrollImportErrors = $result['errors'];
        $this->payrollImportWarnings = $result['warnings'];
        $this->payrollImportMessage = 'Upload completed. Success: '.$result['success_count']
            .', Errors: '.$result['error_count']
            .', Warnings: '.count($result['warnings']).'.';

        if ($result['updated'] !== []) {
            $this->payrollImportMessage .= ' Updated: '.implode(', ', $result['updated']).'.';
        }

        $this->payrollExcelFile = null;
        $this->resetPage();
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
                'p.first_name as first_name',
                'p.last_name as last_name',
                'p.photo_path as photo_path',
                'p.nic as person_nic',
                'ou.name as department_name',
                'pou.name as parent_unit_name',
                'j.name as designation_name',
                'g.name as grade_name',
            ])
            ->where('employments.hrmis_active', $this->hrmisActiveFilter())
            ->join('persons as p', 'p.id', '=', 'employments.person_id')
            ->leftJoin('assignments as a', function (JoinClause $join) use ($ref, $today) {
                $join->on('a.employment_id', '=', 'employments.id')
                    ->whereRaw("a.effective_from <= {$ref}", [$today])
                    ->whereRaw("(a.effective_to IS NULL OR a.effective_to >= {$ref})", [$today]);
            })
            ->leftJoin('org_units as ou', 'ou.id', '=', 'a.org_unit_id')
            ->leftJoin('org_units as pou', 'pou.id', '=', 'ou.parent_id')
            ->leftJoin('designations as j', 'j.id', '=', 'a.designation_id')
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

        if ($this->designationId !== null && $this->designationId !== '') {
            $query->where('a.designation_id', (int) $this->designationId);
        }

        if ($this->employeeCategoryId !== null && $this->employeeCategoryId !== '') {
            $query->where('employments.employee_category_id', (int) $this->employeeCategoryId);
        }

        if (EmploymentType::tryFrom($this->type) !== null) {
            $query->where('employments.type', $this->type);
        }

        if (EmploymentStatus::tryFrom($this->status) !== null) {
            $query->where('employments.status', $this->status);
        }

        if ($this->payrollActive === '1' || $this->payrollActive === '0') {
            $query->where('employments.payroll_active', $this->payrollActive === '1');
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

        $editing = $this->editingEmploymentId === null
            ? null
            : Employment::query()->with(['person', 'employeeCategory', 'staffWorkCategory'])->find($this->editingEmploymentId);

        return view('corehr::livewire.employees-index', [
            'employees' => $employees,
            'orgUnits' => OrgUnitOptions::forCompany(CompanyContext::id()),
            'designations' => Designation::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'employeeCategories' => EmployeeCategory::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'types' => EmploymentType::cases(),
            'statuses' => [
                'ACTIVE' => 'Active',
                'SUSPENDED' => 'Suspended',
                'EXITED' => 'Exited',
                'ALL' => 'All',
            ],
            'emptyMessage' => $this->emptyMessage(),
            'showHireButton' => $this->showHireButton(),
            'showPayrollActiveFilter' => ! $this->hrmisActiveFilter(),
            'editing' => $editing,
            'editSteps' => [
                1 => 'Personal details',
                2 => 'Employment',
                3 => 'Pay and bank',
            ],
        ])->layout('components.layouts.module', [
            'moduleName' => 'Employees',
            'title' => $this->pageTitle(),
            'moduleNav' => EmployeesNav::items($this->navKey()),
        ]);
    }

    protected function hrmisActiveFilter(): bool
    {
        return true;
    }

    protected function pageTitle(): string
    {
        return 'Employees';
    }

    protected function navKey(): string
    {
        return 'employees';
    }

    protected function emptyMessage(): string
    {
        return 'No employees match these filters.';
    }

    protected function showHireButton(): bool
    {
        return true;
    }

    public function servicePeriod(Employment $employment): string
    {
        $from = CarbonImmutable::parse($employment->hire_date->toDateString());
        $to = $employment->exit_date !== null
            ? CarbonImmutable::parse($employment->exit_date->toDateString())
            : CarbonImmutable::now(config('app.timezone'))->startOfDay();

        if ($to->lessThan($from)) {
            return 'Starts '.$from->format('Y-m-d');
        }

        $years = $from->diffInYears($to);
        $months = $from->addYears((int) $years)->diffInMonths($to);
        $days = $from->addYears((int) $years)->addMonths((int) $months)->diffInDays($to);

        $parts = [];
        if ((int) $years > 0) {
            $parts[] = (int) $years.' '.((int) $years === 1 ? 'year' : 'years');
        }
        if ((int) $months > 0) {
            $parts[] = (int) $months.' '.((int) $months === 1 ? 'month' : 'months');
        }
        if ($parts === []) {
            $parts[] = (int) $days.' '.((int) $days === 1 ? 'day' : 'days');
        }

        return implode(' ', $parts);
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
}
