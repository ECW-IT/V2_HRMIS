<?php

namespace Modules\LegacyImport\Services\Pay;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\CoreHr\Enums\EmploymentType;
use Modules\CoreHr\Models\Employment;
use Modules\LegacyImport\Models\ImportRun;
use Modules\LegacyImport\Models\StgIssue;
use Modules\LegacyImport\Models\StgPayComponent;
use Modules\LegacyImport\Models\StgPayProfile;
use Modules\LegacyImport\Services\MappingRepository;
use Modules\LegacyImport\Support\LegacyPayrollConnection;
use Modules\Organisation\Models\Company;

final class PayValidator
{
    public function __construct(private readonly MappingRepository $maps) {}

    /** @return array{run: ImportRun, counts: array<string, int>} */
    public function run(?string $companyCode = null): array
    {
        LegacyPayrollConnection::assertConfigured();
        $this->maps->forget();

        $run = $this->latestPayRun($companyCode);
        if ($run === null) {
            throw new \RuntimeException('No pay staging run found. Run hrmis:import:pay-stage first.');
        }

        StgIssue::query()->where('import_run_id', $run->id)->delete();

        $company = null;
        if ($companyCode !== null && $companyCode !== '') {
            $company = Company::query()->where('code', $companyCode)->firstOrFail();
        } elseif (! empty($run->summary['company_code'])) {
            $company = Company::query()->where('code', $run->summary['company_code'])->first();
        }

        $profiles = StgPayProfile::query()
            ->where('import_run_id', $run->id)
            ->when($company !== null, fn ($q) => $q->where('company_id', $company->id))
            ->with(['components', 'loans', 'advances'])
            ->get();

        StgPayProfile::query()->where('import_run_id', $run->id)->update(['blocked' => false]);
        StgPayComponent::query()->where('import_run_id', $run->id)->update(['blocked' => false]);

        $issues = [];

        // PAY001 / PAY004
        $byEmpNo = $profiles->groupBy(fn (StgPayProfile $p) => ($p->company_id ?? 0).'|'.$p->emp_no);
        foreach ($byEmpNo as $group) {
            if ($group->count() > 1) {
                foreach ($group as $p) {
                    $issues[] = $this->issue($run->id, $p->legacy_profile_id, 'PAY004', 'ERROR', 'Two active payroll profiles for one employee number in the same company.');
                    $p->update(['blocked' => true]);
                }
            }
        }

        foreach ($profiles as $profile) {
            if ($profile->employment_id === null) {
                $issues[] = $this->issue($run->id, $profile->legacy_profile_id, 'PAY001', 'WARNING', 'Active payroll profile with no matching v2 employment.');
            }

            $this->validateProfileAmounts($run->id, $profile, $issues);
            $this->validateComponents($run->id, $profile, $issues);
            $this->validateLoans($run->id, $profile, $issues);
            $this->validateAdvances($run->id, $profile, $issues);
        }

        // PAY002 — active v2 employments with no payroll profile
        if ($company !== null) {
            $matchedEmpNos = $profiles->pluck('emp_no')->filter()->all();
            $missing = Employment::query()
                ->where('company_id', $company->id)
                ->where('status', 'ACTIVE')
                ->whereNotNull('legacy_employee_no')
                ->where('legacy_employee_no', '!=', '')
                ->whereNotIn('legacy_employee_no', $matchedEmpNos)
                ->get(['id', 'legacy_employee_no']);

            foreach ($missing as $e) {
                $issues[] = $this->issue($run->id, null, 'PAY002', 'WARNING', 'Active v2 employment '.$e->legacy_employee_no.' with no payroll profile.');
            }
        }

        foreach ($issues as $issue) {
            StgIssue::query()->create($issue);
        }

        $counts = StgIssue::query()
            ->where('import_run_id', $run->id)
            ->selectRaw('code, count(*) as c')
            ->groupBy('code')
            ->pluck('c', 'code')
            ->map(fn ($c) => (int) $c)
            ->all();

        $run->update([
            'stage' => 'PAY_CHECK',
            'finished_at' => now(),
            'summary' => array_merge($run->summary ?? [], ['issue_counts' => $counts]),
        ]);

        return ['run' => $run->fresh(), 'counts' => $counts];
    }

    private function latestPayRun(?string $companyCode): ?ImportRun
    {
        return ImportRun::query()
            ->whereIn('stage', ['PAY_STAGED', 'PAY_CHECK', 'PAY_LOADED'])
            ->when($companyCode, function ($q) use ($companyCode) {
                $q->where('summary->company_code', $companyCode);
            })
            ->orderByDesc('id')
            ->first();
    }

    /** @param  list<array<string, mixed>>  $issues */
    private function validateProfileAmounts(int $runId, StgPayProfile $profile, array &$issues): void
    {
        $employment = $profile->employment_id
            ? Employment::query()->find($profile->employment_id)
            : null;

        $isIntern = $employment !== null
            && (($employment->type instanceof EmploymentType ? $employment->type : EmploymentType::from((string) $employment->type)) === EmploymentType::INTERN);

        $basic = $profile->basic_salary;
        if (! $isIntern && ($basic === null || ! is_numeric((string) $basic) || bccomp((string) $basic, '0.00', 2) <= 0)) {
            $issues[] = $this->issue($runId, $profile->legacy_profile_id, 'PAY003', 'ERROR', 'Basic salary missing or zero for a non-intern.');
            $profile->update(['blocked' => true]);
        }

        if ($basic !== null && ! is_numeric((string) $basic)) {
            $issues[] = $this->issue($runId, $profile->legacy_profile_id, 'PAY012', 'ERROR', 'Negative or non-numeric amount.');
            $profile->update(['blocked' => true]);
        } elseif ($basic !== null && is_numeric((string) $basic) && bccomp((string) $basic, '0.00', 2) < 0) {
            $issues[] = $this->issue($runId, $profile->legacy_profile_id, 'PAY012', 'ERROR', 'Negative or non-numeric amount.');
            $profile->update(['blocked' => true]);
        }

        $mode = strtolower((string) ($profile->ot_rate_mode ?? ''));
        if ($mode === 'fixed') {
            $otTypeId = $this->maps->payComponentTypeIdForAction('OT_RATE');
            $otRow = $profile->components->first(fn (StgPayComponent $c) => (int) $c->legacy_adjustment_type_id === (int) $otTypeId);
            $otAmount = $otRow?->amount;
            if ($otAmount === null || ! is_numeric((string) $otAmount) || bccomp((string) $otAmount, '0.00', 2) <= 0) {
                $issues[] = $this->issue($runId, $profile->legacy_profile_id, 'PAY007', 'ERROR', 'Fixed OT mode but no OT rate amount.');
                $profile->update(['blocked' => true]);
            }
        }

        $att = trim((string) ($profile->attendance_type ?? ''));
        if ($att !== '' && ! in_array($att, ['type1', 'type2', 'type3', 'type4'], true)) {
            $issues[] = $this->issue($runId, $profile->legacy_profile_id, 'PAY008', 'WARNING', 'Unknown attendance_type value.');
        }

        if ((int) $profile->hostal === 1) {
            $hostelTypeId = $this->maps->payComponentTypeIdForV2Code('HOSTEL');
            if ($hostelTypeId === null) {
                $issues[] = $this->issue($runId, $profile->legacy_profile_id, 'PAY011', 'ERROR', 'Hostel flagged but no active hostel rate.');
            } else {
                $rate = DB::connection('legacy_payroll')
                    ->table('payr_adjustment_rates')
                    ->where('adjustment_id', $hostelTypeId)
                    ->where('status', 1)
                    ->orderByDesc('start_date')
                    ->first();
                if ($rate === null) {
                    $issues[] = $this->issue($runId, $profile->legacy_profile_id, 'PAY011', 'ERROR', 'Hostel flagged but no active hostel rate.');
                }
            }
        }
    }

    /** @param  list<array<string, mixed>>  $issues */
    private function validateComponents(int $runId, StgPayProfile $profile, array &$issues): void
    {
        foreach ($profile->components as $component) {
            $action = strtoupper((string) ($component->action ?? ''));
            if ($action === '') {
                $issues[] = $this->issue($runId, $profile->legacy_profile_id, 'PAY005', 'ERROR', 'Adjustment type not in the mapping file.');
                $component->update(['blocked' => true]);

                continue;
            }

            if (in_array($action, ['ENTRY', 'IGNORE'], true)) {
                $issues[] = $this->issue($runId, $profile->legacy_profile_id, 'PAY006', 'WARNING', 'Adjustment type mapped '.$action.' — not loaded.');
            }

            $amount = $component->amount;
            if ($amount !== null && (! is_numeric((string) $amount) || bccomp((string) $amount, '0.00', 2) < 0)) {
                $issues[] = $this->issue($runId, $profile->legacy_profile_id, 'PAY012', 'ERROR', 'Negative or non-numeric amount.');
                $component->update(['blocked' => true]);
            }
        }
    }

    /** @param  list<array<string, mixed>>  $issues */
    private function validateLoans(int $runId, StgPayProfile $profile, array &$issues): void
    {
        foreach ($profile->loans as $loan) {
            $approvedSignal = (int) $loan->if_approved === 1;
            $statusSignal = (int) $loan->status === 1;
            $amountSignal = $loan->approved_amount !== null
                && is_numeric((string) $loan->approved_amount)
                && bccomp((string) $loan->approved_amount, '0.00', 2) > 0;
            if ($approvedSignal !== $statusSignal || $approvedSignal !== $amountSignal) {
                $issues[] = $this->issue($runId, $profile->legacy_profile_id, 'PAY009', 'ERROR', 'Loan approval fields disagree.');
                $loan->update(['blocked' => true]);
            }

            $fromProbe = CarbonImmutable::now(config('app.timezone'))->startOfMonth()->toDateString();
            foreach ($loan->unpaid_schedule ?? [] as $row) {
                $month = (string) ($row['month'] ?? '');
                if ($month !== '' && $month < $fromProbe) {
                    $issues[] = $this->issue($runId, $profile->legacy_profile_id, 'PAY010', 'WARNING', 'Unpaid loan instalment dated before the opening month.');
                }
                $amt = $row['amount'] ?? null;
                if ($amt !== null && (! is_numeric((string) $amt) || bccomp((string) $amt, '0.00', 2) < 0)) {
                    $issues[] = $this->issue($runId, $profile->legacy_profile_id, 'PAY012', 'ERROR', 'Negative or non-numeric amount.');
                    $loan->update(['blocked' => true]);
                }
            }
        }
    }

    /** @param  list<array<string, mixed>>  $issues */
    private function validateAdvances(int $runId, StgPayProfile $profile, array &$issues): void
    {
        $fromProbe = CarbonImmutable::now(config('app.timezone'))->startOfMonth()->toDateString();
        foreach ($profile->advances as $advance) {
            foreach ($advance->unpaid_deductions ?? [] as $row) {
                $month = (string) ($row['month'] ?? '');
                if ($month !== '' && $month < $fromProbe) {
                    $issues[] = $this->issue($runId, $profile->legacy_profile_id, 'PAY010', 'WARNING', 'Unpaid advance recovery dated before the opening month.');
                }
            }
            $amt = $advance->unpaid_amount;
            if ($amt !== null && (! is_numeric((string) $amt) || bccomp((string) $amt, '0.00', 2) < 0)) {
                $issues[] = $this->issue($runId, $profile->legacy_profile_id, 'PAY012', 'ERROR', 'Negative or non-numeric amount.');
                $advance->update(['blocked' => true]);
            }
        }
    }

    /** @return array<string, mixed> */
    private function issue(int $runId, ?int $legacyId, string $code, string $severity, string $message): array
    {
        return [
            'import_run_id' => $runId,
            'legacy_hrm_id' => $legacyId,
            'code' => $code,
            'severity' => $severity,
            'message' => $message,
        ];
    }
}
