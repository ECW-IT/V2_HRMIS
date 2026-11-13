<?php

namespace Modules\LegacyImport\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use Modules\CoreHr\Models\Employment;
use Modules\CoreHr\Readiness\ReadinessService;
use Modules\LegacyImport\Exports\ReconciliationExport;
use Modules\LegacyImport\Models\ImportRun;
use Modules\LegacyImport\Models\StgEmployee;
use Modules\LegacyImport\Models\StgIssue;
use Modules\Organisation\Models\Company;

final class Reconciler
{
    public function __construct(
        private readonly MappingRepository $maps,
        private readonly ReadinessService $readiness,
    ) {}

    /**
     * @return array{
     *   path: string,
     *   summary: array<string, array{staged: int, loaded: int, failed: int, legacy_active: int}>,
     *   readiness: array<string, array<string, int>>
     * }
     */
    public function run(?int $runId = null): array
    {
        $this->maps->forget();
        $run = $this->resolveRun($runId);

        $employees = StgEmployee::query()->where('import_run_id', $run->id)->get();
        $issues = StgIssue::query()->where('import_run_id', $run->id)->orderBy('id')->get();

        $legacyByCompany = $this->legacyActiveByCompany();

        $summary = [];
        $companies = $employees->pluck('company_code')->filter()->unique()->values();
        foreach ($companies as $code) {
            $subset = $employees->where('company_code', $code);
            $loaded = $subset->whereNotNull('loaded_employment_id')->count();
            $failed = $subset->filter(fn (StgEmployee $e) => $e->loaded_employment_id === null && $e->load_error !== null)->count();
            // Failed also includes not loaded without error? Balancing: staged = loaded + failed
            $failed = $subset->count() - $loaded;
            $summary[$code] = [
                'staged' => $subset->count(),
                'loaded' => $loaded,
                'failed' => $failed,
                'legacy_active' => $legacyByCompany[$code] ?? 0,
            ];
        }

        $totalStaged = $employees->count();
        $totalLoaded = $employees->whereNotNull('loaded_employment_id')->count();
        $totalFailed = $totalStaged - $totalLoaded;
        $totalLegacy = array_sum($legacyByCompany);
        $summary['TOTAL'] = [
            'staged' => $totalStaged,
            'loaded' => $totalLoaded,
            'failed' => $totalFailed,
            'legacy_active' => $totalLegacy,
        ];

        $readiness = [];
        foreach ($companies as $code) {
            $company = Company::query()->where('code', $code)->first();
            if ($company === null) {
                continue;
            }
            $asOf = now(config('app.timezone'))->toDateString();
            $readiness[$code] = collect($this->readiness->summary($company, $asOf))
                ->mapWithKeys(fn ($row, $codeKey) => [$codeKey => (int) $row['count']])
                ->all();
        }

        $summaryRows = [];
        foreach ($summary as $company => $row) {
            foreach (['staged', 'loaded', 'failed', 'legacy_active'] as $metric) {
                $summaryRows[] = [$metric, $company, $row[$metric]];
            }
        }

        foreach ($employees->groupBy('company_code') as $code => $subset) {
            if ($code === null || $code === '') {
                continue;
            }
            $types = Employment::withoutGlobalScopes()
                ->whereIn('id', $subset->pluck('loaded_employment_id')->filter())
                ->get()
                ->groupBy(fn ($e) => $e->type->value);
            foreach ($types as $type => $group) {
                $summaryRows[] = ["type:{$type}", $code, $group->count()];
            }
        }

        foreach ($readiness as $code => $counts) {
            foreach ($counts as $blocker => $count) {
                $summaryRows[] = ["readiness:{$blocker}", $code, $count];
            }
        }

        $issueRows = $issues->map(fn (StgIssue $i) => [
            $i->legacy_hrm_id,
            $i->code,
            $i->severity,
            $i->message,
        ])->all();

        $failureRows = $employees
            ->filter(fn (StgEmployee $e) => $e->load_error !== null)
            ->map(fn (StgEmployee $e) => [
                $e->legacy_hrm_id,
                $e->normalised_employee_no,
                $e->load_error,
            ])->values()->all();

        $bankRows = collect($run->summary['bank_accounts_to_complete'] ?? [])
            ->map(fn ($r) => [$r['employee_no'], $r['name'], $r['account_no']])
            ->all();

        $relative = 'import/reconciliation-'.$run->id.'.xlsx';
        Storage::disk('private')->makeDirectory('import');
        Excel::store(
            new ReconciliationExport([
                'summary_rows' => $summaryRows,
                'issues' => $issueRows,
                'load_failures' => $failureRows,
                'bank_accounts' => $bankRows,
            ]),
            $relative,
            'private'
        );

        $run->update([
            'summary' => array_merge($run->summary ?? [], [
                'reconciliation' => $summary,
                'readiness' => $readiness,
            ]),
        ]);

        return [
            'path' => Storage::disk('private')->path($relative),
            'summary' => $summary,
            'readiness' => $readiness,
        ];
    }

    /** @return array<string, int> */
    private function legacyActiveByCompany(): array
    {
        $counts = [];
        $rows = DB::connection('legacy_hrm')
            ->table('employees')
            ->where('is_resigned', 0)
            ->where('deleted', 0)
            ->get(['id', 'emp_branch']);

        foreach ($rows as $row) {
            $code = $this->maps->companyCodeForBranch($row->emp_branch);
            if ($code === null) {
                continue;
            }
            $counts[$code] = ($counts[$code] ?? 0) + 1;
        }

        return $counts;
    }

    private function resolveRun(?int $runId): ImportRun
    {
        if ($runId !== null) {
            return ImportRun::query()->findOrFail($runId);
        }

        return ImportRun::query()->orderByDesc('id')->firstOrFail();
    }
}
