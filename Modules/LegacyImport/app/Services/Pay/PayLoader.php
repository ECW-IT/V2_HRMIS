<?php

namespace Modules\LegacyImport\Services\Pay;

use App\Models\User;
use App\Support\Audit\AuditLogger;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use Modules\Calendar\Services\PayPeriodService;
use Modules\CoreHr\Enums\EmploymentType;
use Modules\CoreHr\Models\Employment;
use Modules\LegacyImport\Exports\PayReconciliationExport;
use Modules\LegacyImport\Models\ImportRun;
use Modules\LegacyImport\Models\StgIssue;
use Modules\LegacyImport\Models\StgPayProfile;
use Modules\LegacyImport\Services\MappingRepository;
use Modules\LegacyImport\Support\LegacyPayrollConnection;
use Modules\Organisation\Models\Company;
use Modules\Payroll\Enums\OtRateMode;
use Modules\Payroll\Models\EmployeeCompensation;
use Modules\Payroll\Services\AdvanceService;
use Modules\Payroll\Services\CompensationService;
use Modules\Payroll\Services\LoanService;
use RuntimeException;
use Throwable;

final class PayLoader
{
    public function __construct(
        private readonly MappingRepository $maps,
        private readonly CompensationService $compensation,
        private readonly LoanService $loans,
        private readonly AdvanceService $advances,
        private readonly PayPeriodService $periods,
    ) {}

    /**
     * @return array{run: ImportRun, path: string, loaded: int, skipped: int, dry_run: bool}
     */
    public function run(string $from, ?string $companyCode = null, bool $dryRun = false, ?User $by = null): array
    {
        LegacyPayrollConnection::assertConfigured();
        $this->maps->forget();

        $from = CarbonImmutable::parse($from)->startOfMonth()->toDateString();
        $company = $this->resolveCompany($companyCode);

        if (! $dryRun && EmployeeCompensation::query()->where('company_id', $company->id)->exists()) {
            throw new RuntimeException('This company already has compensation rows. Refusing to load (use --dry-run to preview).');
        }

        $run = ImportRun::query()
            ->whereIn('stage', ['PAY_STAGED', 'PAY_CHECK', 'PAY_LOADED'])
            ->where('summary->company_code', $company->code)
            ->orderByDesc('id')
            ->first();

        if ($run === null) {
            throw new RuntimeException('No pay staging run found. Run hrmis:import:pay-stage first.');
        }

        if ($by === null) {
            throw new RuntimeException('An authenticated user is required to load opening pay data.');
        }

        $period = $this->periods->ensure($company, (int) substr($from, 0, 4), (int) substr($from, 5, 2));

        $profiles = StgPayProfile::query()
            ->where('import_run_id', $run->id)
            ->where('company_id', $company->id)
            ->with(['components', 'loans', 'advances', 'employment'])
            ->orderBy('id')
            ->get();

        $loadedRows = [];
        $loaded = 0;
        $skipped = 0;
        $pay010Written = false;

        foreach ($profiles as $profile) {
            if ($profile->blocked || $profile->employment_id === null) {
                $skipped++;

                continue;
            }

            $employment = Employment::query()->find($profile->employment_id);
            if ($employment === null) {
                $skipped++;

                continue;
            }

            try {
                $summary = AuditLogger::withReason('Opening data from legacy payroll', function () use (
                    $profile,
                    $employment,
                    $from,
                    $period,
                    $by,
                    $dryRun,
                    $run,
                    &$pay010Written,
                ) {
                    return $this->loadOne($profile, $employment, $from, $period, $by, $dryRun, $run, $pay010Written);
                });
            } catch (Throwable $e) {
                $profile->update(['load_error' => $e->getMessage()]);
                StgIssue::query()->create([
                    'import_run_id' => $run->id,
                    'legacy_hrm_id' => $profile->legacy_profile_id,
                    'code' => 'PAY012',
                    'severity' => 'ERROR',
                    'message' => $e->getMessage(),
                ]);
                $skipped++;

                continue;
            }

            if ($summary === null) {
                $skipped++;

                continue;
            }

            $loadedRows[] = [
                $profile->emp_no,
                $summary['basic'],
                $summary['components_count'],
                $summary['components_total'],
                $summary['loans_count'],
                $summary['loans_outstanding'],
                $summary['advances_count'],
                $summary['advances_amount'],
            ];
            if (! $dryRun) {
                $profile->update(['loaded' => true, 'load_error' => null]);
            }
            $loaded++;
        }

        $issueSheets = [];
        foreach (StgIssue::query()->where('import_run_id', $run->id)->orderBy('id')->get() as $issue) {
            $issueSheets[$issue->code][] = [
                $issue->legacy_hrm_id,
                StgPayProfile::query()->where('import_run_id', $run->id)->where('legacy_profile_id', $issue->legacy_hrm_id)->value('emp_no'),
                $issue->message,
            ];
        }

        $export = new PayReconciliationExport([
            'issue_sheets' => $issueSheets,
            'loaded' => $loadedRows,
        ]);

        $stamp = now()->format('Ymd-His');
        $relative = "import/pay-reconciliation-{$company->code}-{$stamp}.xlsx";
        Excel::store($export, $relative, 'private');

        if (! $dryRun) {
            $run->update([
                'stage' => 'PAY_LOADED',
                'finished_at' => now(),
                'summary' => array_merge($run->summary ?? [], [
                    'loaded' => $loaded,
                    'skipped' => $skipped,
                    'reconciliation' => $relative,
                ]),
            ]);
        }

        return [
            'run' => $run->fresh(),
            'path' => Storage::disk('private')->path($relative),
            'loaded' => $loaded,
            'skipped' => $skipped,
            'dry_run' => $dryRun,
        ];
    }

    /**
     * @return array{basic: string, components_count: int, components_total: string, loans_count: int, loans_outstanding: string, advances_count: int, advances_amount: string}|null
     */
    private function loadOne(
        StgPayProfile $profile,
        Employment $employment,
        string $from,
        $period,
        User $by,
        bool $dryRun,
        ImportRun $run,
        bool &$pay010Written,
    ): ?array {
        if ($profile->blocked) {
            return null;
        }

        $type = $employment->type instanceof EmploymentType
            ? $employment->type
            : EmploymentType::from((string) $employment->type);

        $basic = $type === EmploymentType::INTERN
            ? '0.00'
            : number_format((float) $profile->basic_salary, 2, '.', '');

        $modeRaw = strtolower((string) ($profile->ot_rate_mode ?? 'calculated'));
        $mode = $modeRaw === 'fixed' ? OtRateMode::FIXED : OtRateMode::CALCULATED;
        $otRate = null;
        if ($mode === OtRateMode::FIXED) {
            $otTypeId = $this->maps->payComponentTypeIdForAction('OT_RATE');
            $otRow = $profile->components->first(fn ($c) => (int) $c->legacy_adjustment_type_id === (int) $otTypeId);
            $otRate = number_format((float) ($otRow?->amount ?? 0), 2, '.', '');
        }

        $tier = null;
        $att = trim((string) ($profile->attendance_type ?? ''));
        $attTypeId = $this->maps->payComponentTypeIdForV2Code('ATT_ALLOW');
        $hasAtt = $attTypeId !== null && $profile->components->contains(
            fn ($c) => (int) $c->legacy_adjustment_type_id === (int) $attTypeId
        );
        if ($hasAtt && in_array($att, ['type1', 'type2', 'type3', 'type4'], true)) {
            $tier = 'T'.substr($att, -1);
        }

        if (! $dryRun) {
            $this->compensation->set($employment, $from, [
                'basic_salary' => $basic,
                'ot_rate_mode' => $mode,
                'ot_rate' => $otRate,
                'attendance_tier_code' => $tier,
                'change_reason' => 'Opening data from legacy payroll',
            ]);
        }

        $componentsCount = 0;
        $componentsTotal = '0.00';

        foreach ($profile->components as $component) {
            if ($component->blocked || strtoupper((string) $component->action) !== 'COMPONENT') {
                continue;
            }
            $amount = number_format((float) ($component->amount ?? 0), 2, '.', '');
            if (bccomp($amount, '0.00', 2) <= 0) {
                continue;
            }
            $code = (string) $component->v2_code;
            if ($code === '') {
                continue;
            }
            if (! $dryRun) {
                try {
                    $this->compensation->addComponent($employment, $code, $amount, $from);
                } catch (Throwable $e) {
                    StgIssue::query()->create([
                        'import_run_id' => $run->id,
                        'legacy_hrm_id' => $profile->legacy_profile_id,
                        'code' => 'PAY005',
                        'severity' => 'ERROR',
                        'message' => $e->getMessage(),
                    ]);

                    continue;
                }
            }
            $componentsCount++;
            $componentsTotal = bcadd($componentsTotal, $amount, 2);
        }

        if ((int) $profile->hostal === 1) {
            $hostelTypeId = $this->maps->payComponentTypeIdForV2Code('HOSTEL');
            $rate = $hostelTypeId !== null
                ? DB::connection('legacy_payroll')
                    ->table('payr_adjustment_rates')
                    ->where('adjustment_id', $hostelTypeId)
                    ->where('status', 1)
                    ->orderByDesc('start_date')
                    ->first()
                : null;
            if ($rate !== null) {
                $hostelAmount = number_format((float) $rate->amount, 2, '.', '');
                if (! $dryRun) {
                    $this->compensation->addComponent($employment, 'HOSTEL', $hostelAmount, $from);
                }
                $componentsCount++;
                $componentsTotal = bcadd($componentsTotal, $hostelAmount, 2);
            }
        }

        $loansCount = 0;
        $loansOutstanding = '0.00';
        foreach ($profile->loans as $loan) {
            if ($loan->blocked || (int) $loan->if_approved !== 1 || (int) $loan->paid !== 1) {
                continue;
            }
            $instalments = [];
            foreach ($loan->unpaid_schedule ?? [] as $row) {
                $month = CarbonImmutable::parse((string) $row['month'])->startOfMonth()->toDateString();
                if ($month < $from) {
                    StgIssue::query()->create([
                        'import_run_id' => $run->id,
                        'legacy_hrm_id' => $profile->legacy_profile_id,
                        'code' => 'PAY010',
                        'severity' => 'WARNING',
                        'message' => 'Unpaid loan instalment dated before '.$from.' moved to opening month.',
                    ]);
                    $pay010Written = true;
                    $month = $from;
                }
                $instalments[] = [
                    'due_month' => $month,
                    'amount' => number_format((float) $row['amount'], 2, '.', ''),
                ];
            }
            if ($instalments === []) {
                continue;
            }
            $outstanding = number_format((float) ($loan->outstanding ?? 0), 2, '.', '');
            if (! $dryRun) {
                $this->loans->loadOpening($employment, [
                    'amount' => $outstanding,
                    'instalments' => $instalments,
                    'paid_on' => $from,
                ], $by);
            }
            $loansCount++;
            $loansOutstanding = bcadd($loansOutstanding, $outstanding, 2);
        }

        $advancesCount = 0;
        $advancesAmount = '0.00';
        foreach ($profile->advances as $advance) {
            if ($advance->blocked) {
                continue;
            }
            foreach ($advance->unpaid_deductions ?? [] as $row) {
                $month = (string) ($row['month'] ?? '');
                if ($month !== '' && $month < $from) {
                    StgIssue::query()->create([
                        'import_run_id' => $run->id,
                        'legacy_hrm_id' => $profile->legacy_profile_id,
                        'code' => 'PAY010',
                        'severity' => 'WARNING',
                        'message' => 'Unpaid advance recovery dated before '.$from.' moved to opening month.',
                    ]);
                    $pay010Written = true;
                }
            }
            $amount = number_format((float) ($advance->unpaid_amount ?? 0), 2, '.', '');
            if (bccomp($amount, '0.00', 2) <= 0) {
                continue;
            }
            if (! $dryRun) {
                $this->advances->loadOpening($employment, $period, $amount, $by, $from);
            }
            $advancesCount++;
            $advancesAmount = bcadd($advancesAmount, $amount, 2);
        }

        return [
            'basic' => $basic,
            'components_count' => $componentsCount,
            'components_total' => $componentsTotal,
            'loans_count' => $loansCount,
            'loans_outstanding' => $loansOutstanding,
            'advances_count' => $advancesCount,
            'advances_amount' => $advancesAmount,
        ];
    }

    private function resolveCompany(?string $companyCode): Company
    {
        if ($companyCode === null || $companyCode === '') {
            throw new RuntimeException('The --company option is required for pay-load.');
        }

        $company = Company::query()->where('code', $companyCode)->first();
        if ($company === null) {
            throw new RuntimeException("Unknown company code: {$companyCode}");
        }

        return $company;
    }
}
