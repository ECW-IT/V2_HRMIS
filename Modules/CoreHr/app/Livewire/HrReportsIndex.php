<?php

namespace Modules\CoreHr\Livewire;

use App\Support\Company\CompanyContext;
use Illuminate\View\View;
use Livewire\Component;
use Maatwebsite\Excel\Facades\Excel;
use Modules\CoreHr\Exports\HrReportExport;
use Modules\CoreHr\Models\Employment;
use Modules\CoreHr\Services\HrReportService;
use Modules\CoreHr\Support\EmployeesNav;
use Modules\Organisation\Models\Company;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class HrReportsIndex extends Component
{
    public string $reportType = 'active';

    public string|int|null $employmentId = null;

    public string $dateFrom = '';

    public string $dateTo = '';

    public string $month = '';

    public string $leaveMode = 'range';

    public string $leaveDate = '';

    public ?string $error = null;

    /** @var list<string> */
    public array $headings = [];

    /** @var list<list<string|null>> */
    public array $rows = [];

    public string $reportTitle = '';

    public function boot(): void
    {
        CompanyContext::ensureSoleSelected();
    }

    public function mount(): void
    {
        $this->authorize('employee.view');
        $now = now(config('app.timezone'));
        $this->month = $now->format('Y-m');
        $this->dateFrom = $now->copy()->startOfMonth()->toDateString();
        $this->dateTo = $now->toDateString();
        $this->leaveDate = $now->toDateString();
    }

    public function updatedReportType(): void
    {
        $this->error = null;
        $this->headings = [];
        $this->rows = [];
        $this->reportTitle = '';
    }

    public function runReport(HrReportService $reports): void
    {
        $this->authorize('employee.view');
        $this->error = null;

        try {
            $result = $reports->run($this->reportType, $this->company(), $this->filters());
        } catch (RuntimeException $e) {
            $this->error = $e->getMessage();
            $this->headings = [];
            $this->rows = [];
            $this->reportTitle = '';

            return;
        }

        $this->reportTitle = $result['title'];
        $this->headings = $result['headings'];
        $this->rows = $result['rows'];
    }

    public function download(HrReportService $reports): BinaryFileResponse
    {
        $this->authorize('employee.view');
        $result = $reports->run($this->reportType, $this->company(), $this->filters());
        $slug = str_replace(' ', '-', strtolower($this->reportType));

        return Excel::download(
            new HrReportExport($result['title'], $result['headings'], $result['rows']),
            'hr-report-'.$slug.'.xlsx',
        );
    }

    /** @return array<string, mixed> */
    private function filters(): array
    {
        return [
            'employment_id' => $this->employmentId !== null && $this->employmentId !== ''
                ? (int) $this->employmentId
                : null,
            'date_from' => $this->dateFrom,
            'date_to' => $this->dateTo,
            'month' => $this->month,
            'leave_mode' => $this->leaveMode,
            'leave_date' => $this->leaveDate,
        ];
    }

    private function company(): Company
    {
        $id = CompanyContext::id();
        if ($id === null) {
            throw new RuntimeException('Select a company.');
        }

        return Company::query()->findOrFail($id);
    }

    public function render(): View
    {
        $this->authorize('employee.view');

        $companyId = CompanyContext::id();
        $employees = $companyId === null
            ? collect()
            : Employment::query()
                ->where('company_id', $companyId)
                ->with('person')
                ->orderBy('employee_no')
                ->get()
                ->mapWithKeys(fn (Employment $e) => [
                    $e->id => $e->employee_no.' — '.$e->person->name_with_initials,
                ]);

        return view('corehr::livewire.hr-reports-index', [
            'reportTypes' => HrReportService::TYPES,
            'employees' => $employees,
        ])->layout('components.layouts.module', [
            'moduleName' => 'Employees',
            'title' => 'HR reports',
            'moduleNav' => EmployeesNav::items('reports'),
        ]);
    }
}
