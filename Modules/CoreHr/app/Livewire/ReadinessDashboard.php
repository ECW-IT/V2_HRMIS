<?php

namespace Modules\CoreHr\Livewire;

use App\Support\Company\CompanyContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Component;
use Maatwebsite\Excel\Facades\Excel;
use Modules\CoreHr\Exports\ReadinessFailingExport;
use Modules\CoreHr\Models\Employment;
use Modules\CoreHr\Readiness\ReadinessService;
use Modules\CoreHr\Readiness\Severity;
use Modules\Organisation\Models\Company;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ReadinessDashboard extends Component
{
    public string $asOf = '';

    public ?string $selectedCode = null;

    public function mount(): void
    {
        $this->asOf = CarbonImmutable::now(config('app.timezone'))->toDateString();
    }

    public function show(string $code): void
    {
        $this->selectedCode = $code;
    }

    public function clearSelection(): void
    {
        $this->selectedCode = null;
    }

    public function export(ReadinessService $readiness): BinaryFileResponse
    {
        $this->authorize('employee.view');
        abort_unless($this->selectedCode !== null, 404);

        $rows = $this->failingRows($readiness);

        return Excel::download(
            new ReadinessFailingExport($rows),
            'readiness-'.$this->selectedCode.'.xlsx',
        );
    }

    public function render(ReadinessService $readiness): View
    {
        $this->authorize('employee.view');

        $companies = $this->companies();
        $asOf = $this->asOf !== '' ? $this->asOf : CarbonImmutable::now(config('app.timezone'))->toDateString();

        $sections = [];
        $totalBlockers = 0;
        $totalWarnings = 0;

        foreach ($companies as $company) {
            $summary = $readiness->summary($company, $asOf);
            $blockers = 0;
            $warnings = 0;
            foreach ($summary as $row) {
                $severity = $row['severity'] instanceof Severity ? $row['severity'] : Severity::from((string) $row['severity']);
                if ($severity === Severity::BLOCKER) {
                    $blockers += $row['count'];
                } else {
                    $warnings += $row['count'];
                }
            }
            $totalBlockers += $blockers;
            $totalWarnings += $warnings;
            $sections[] = [
                'company' => $company,
                'summary' => $summary,
                'blockers' => $blockers,
                'warnings' => $warnings,
            ];
        }

        return view('corehr::livewire.readiness-dashboard', [
            'sections' => $sections,
            'totalBlockers' => $totalBlockers,
            'totalWarnings' => $totalWarnings,
            'failingEmployees' => $this->selectedCode !== null ? $this->failingRows($readiness) : [],
        ])->layout('components.layouts.app', [
            'title' => 'Payroll readiness',
        ]);
    }

    /** @return Collection<int, Company> */
    private function companies(): Collection
    {
        $selected = CompanyContext::id();
        if ($selected !== null) {
            return Company::query()->whereKey($selected)->get();
        }

        return Company::query()
            ->whereIn('id', CompanyContext::permittedIds())
            ->orderBy('id')
            ->get();
    }

    /** @return list<array{employee_no: string, name: string, department: string, job: string}> */
    private function failingRows(ReadinessService $readiness): array
    {
        if ($this->selectedCode === null) {
            return [];
        }

        $asOf = $this->asOf !== '' ? $this->asOf : CarbonImmutable::now(config('app.timezone'))->toDateString();
        $rows = [];

        foreach ($this->companies() as $company) {
            $issues = $readiness->run($company, $asOf)
                ->where('code', $this->selectedCode);

            $ids = $issues->pluck('employmentId')->unique()->values()->all();
            if ($ids === []) {
                continue;
            }

            $employments = Employment::query()
                ->with(['person', 'assignments' => fn ($q) => $q->effectiveOn($asOf)->with(['orgUnit', 'job'])])
                ->whereIn('id', $ids)
                ->get();

            foreach ($employments as $employment) {
                $assignment = $employment->assignments->first();
                $rows[] = [
                    'employee_no' => $employment->employee_no,
                    'name' => trim($employment->person->first_name.' '.$employment->person->last_name),
                    'department' => $assignment?->orgUnit?->name ?? '—',
                    'job' => $assignment?->job?->name ?? '—',
                ];
            }
        }

        return $rows;
    }
}
