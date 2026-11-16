<?php

namespace Modules\LegacyImport\Services\Pay;

use Illuminate\Support\Facades\DB;
use Modules\CoreHr\Models\Employment;
use Modules\LegacyImport\Models\ImportRun;
use Modules\LegacyImport\Models\StgAdvance;
use Modules\LegacyImport\Models\StgLoan;
use Modules\LegacyImport\Models\StgPayComponent;
use Modules\LegacyImport\Models\StgPayProfile;
use Modules\LegacyImport\Services\MappingRepository;
use Modules\LegacyImport\Support\LegacyPayrollConnection;
use Modules\Organisation\Models\Company;

final class PayStager
{
    public function __construct(private readonly MappingRepository $maps) {}

    /** @return array{run: ImportRun, staged: int} */
    public function run(?string $companyCode = null, ?int $runBy = null): array
    {
        LegacyPayrollConnection::assertConfigured();
        $this->maps->forget();

        $company = null;
        if ($companyCode !== null && $companyCode !== '') {
            $company = Company::query()->where('code', $companyCode)->first();
            if ($company === null) {
                throw new \RuntimeException("Unknown company code: {$companyCode}");
            }
        }

        $this->clearPreviousPayStaging($company?->id);

        $run = ImportRun::query()->create([
            'started_at' => now(),
            'finished_at' => null,
            'stage' => 'PAY_STAGED',
            'summary' => ['company_code' => $company?->code],
            'run_by' => $runBy,
        ]);

        $query = DB::connection('legacy_payroll')
            ->table('payr__employee_profile')
            ->where('status', 1)
            ->orderBy('id');

        $profiles = $query->get();
        $staged = 0;

        foreach ($profiles as $row) {
            $p = (array) $row;
            $branchId = $p['branch_id'] ?? null;
            $mappedCode = $this->maps->companyCodeForBranch($branchId);
            $mappedCompany = $mappedCode !== null
                ? Company::query()->where('code', $mappedCode)->first()
                : null;

            if ($company !== null) {
                if ($mappedCompany === null || (int) $mappedCompany->id !== (int) $company->id) {
                    continue;
                }
            }

            $empNo = (string) ($p['emp_no'] ?? '');
            $employmentId = null;
            if ($mappedCompany !== null && $empNo !== '') {
                $employmentId = Employment::query()
                    ->where('company_id', $mappedCompany->id)
                    ->where('legacy_employee_no', $empNo)
                    ->value('id');
            }

            $profile = StgPayProfile::query()->create([
                'import_run_id' => $run->id,
                'legacy_profile_id' => (int) $p['id'],
                'emp_no' => $empNo,
                'company_code' => $mappedCode,
                'company_id' => $mappedCompany?->id,
                'employment_id' => $employmentId !== null ? (int) $employmentId : null,
                'basic_salary' => $p['basic_salary'] ?? null,
                'ot_rate_mode' => $p['ot_rate_mode'] ?? null,
                'attendance_type' => $p['attendance_type'] ?? null,
                'hostal' => (int) ($p['hostal'] ?? 0),
                'branch_id' => $branchId !== null ? (int) $branchId : null,
                'status' => (int) ($p['status'] ?? 1),
                'raw' => $p,
                'blocked' => false,
                'loaded' => false,
            ]);

            $this->stageAdjustments($run->id, $profile);
            $this->stageLoans($run->id, $profile);
            $this->stageAdvances($run->id, $profile);
            $staged++;
        }

        $run->update([
            'finished_at' => now(),
            'summary' => [
                'company_code' => $company?->code,
                'staged' => $staged,
            ],
        ]);

        return ['run' => $run->fresh(), 'staged' => $staged];
    }

    private function clearPreviousPayStaging(?int $companyId): void
    {
        $profileQuery = StgPayProfile::query();
        if ($companyId !== null) {
            $profileQuery->where('company_id', $companyId);
        }

        $profileIds = $profileQuery->pluck('id');
        if ($profileIds->isEmpty() && $companyId !== null) {
            return;
        }

        if ($companyId === null) {
            StgAdvance::query()->delete();
            StgLoan::query()->delete();
            StgPayComponent::query()->delete();
            StgPayProfile::query()->delete();

            return;
        }

        StgAdvance::query()->whereIn('stg_pay_profile_id', $profileIds)->delete();
        StgLoan::query()->whereIn('stg_pay_profile_id', $profileIds)->delete();
        StgPayComponent::query()->whereIn('stg_pay_profile_id', $profileIds)->delete();
        StgPayProfile::query()->whereIn('id', $profileIds)->delete();
    }

    private function stageAdjustments(int $runId, StgPayProfile $profile): void
    {
        $rows = DB::connection('legacy_payroll')
            ->table('payr_employee_adjestments')
            ->where('emp_id', $profile->legacy_profile_id)
            ->where('status', 1)
            ->get();

        $types = DB::connection('legacy_payroll')
            ->table('payr_adjustment_types')
            ->get()
            ->keyBy('id');

        foreach ($rows as $row) {
            $r = (array) $row;
            $typeId = (int) ($r['adju_id'] ?? 0);
            $map = $this->maps->payComponentMap($typeId);
            $type = $types->get($typeId);

            StgPayComponent::query()->create([
                'import_run_id' => $runId,
                'stg_pay_profile_id' => $profile->id,
                'legacy_adju_id' => (int) $r['id'],
                'legacy_adjustment_type_id' => $typeId,
                'legacy_name' => $map['legacy_name'] ?? ($type->name ?? null),
                'v2_code' => $map['v2_code'] ?? null,
                'action' => $map['action'] ?? null,
                'amount' => $r['amount'] ?? null,
                'skip_zero' => false,
                'blocked' => false,
            ]);
        }
    }

    private function stageLoans(int $runId, StgPayProfile $profile): void
    {
        $loans = DB::connection('legacy_payroll')
            ->table('payr_employee_loans')
            ->where('employee_id', $profile->legacy_profile_id)
            ->get();

        foreach ($loans as $loan) {
            $l = (array) $loan;
            $schedules = DB::connection('legacy_payroll')
                ->table('payr_loan_schedules')
                ->where('loan_id', $l['id'])
                ->where('if_paid', 0)
                ->orderBy('schedule_month')
                ->get();

            if ($schedules->isEmpty()) {
                continue;
            }

            $unpaid = [];
            $outstanding = '0.00';
            foreach ($schedules as $s) {
                $sArr = (array) $s;
                $amount = number_format((float) ($sArr['schedule_amount'] ?? 0), 2, '.', '');
                $unpaid[] = [
                    'month' => (string) ($sArr['schedule_month'] ?? ''),
                    'amount' => $amount,
                    'is_grace_month' => (int) ($sArr['is_grace_month'] ?? 0),
                ];
                $outstanding = bcadd($outstanding, $amount, 2);
            }

            StgLoan::query()->create([
                'import_run_id' => $runId,
                'stg_pay_profile_id' => $profile->id,
                'legacy_loan_id' => (int) $l['id'],
                'loan_type_id' => isset($l['loan_type_id']) ? (int) $l['loan_type_id'] : null,
                'approved_amount' => $l['approved_amount'] ?? null,
                'approved_installments' => $l['approved_installments'] ?? null,
                'if_approved' => (int) ($l['if_approved'] ?? 0),
                'status' => (int) ($l['status'] ?? 0),
                'paid' => (int) ($l['paid'] ?? 0),
                'unpaid_schedule' => $unpaid,
                'outstanding' => $outstanding,
                'blocked' => false,
                'loaded' => false,
            ]);
        }
    }

    private function stageAdvances(int $runId, StgPayProfile $profile): void
    {
        $advances = DB::connection('legacy_payroll')
            ->table('payr_salary_advance_requests')
            ->where('employee_id', $profile->legacy_profile_id)
            ->where('is_paid', 1)
            ->whereNull('converted_to_loan_id')
            ->get();

        foreach ($advances as $advance) {
            $a = (array) $advance;
            $deductions = DB::connection('legacy_payroll')
                ->table('payr_advances_deductions')
                ->where('payr_employee_salary_advance_id', $a['id'])
                ->where('if_paid', 0)
                ->get();

            if ($deductions->isEmpty()) {
                continue;
            }

            $unpaid = [];
            $unpaidAmount = '0.00';
            foreach ($deductions as $d) {
                $dArr = (array) $d;
                $amount = number_format((float) ($dArr['deducted_amount'] ?? 0), 2, '.', '');
                $unpaid[] = [
                    'month' => (string) ($dArr['month'] ?? ''),
                    'amount' => $amount,
                ];
                $unpaidAmount = bcadd($unpaidAmount, $amount, 2);
            }

            StgAdvance::query()->create([
                'import_run_id' => $runId,
                'stg_pay_profile_id' => $profile->id,
                'legacy_advance_id' => (int) $a['id'],
                'amount' => $a['amount'] ?? null,
                'unpaid_amount' => $unpaidAmount,
                'is_paid' => (int) ($a['is_paid'] ?? 0),
                'paid_date' => $a['paid_date'] ?? null,
                'status' => (int) ($a['status'] ?? 0),
                'converted_to_loan_id' => null,
                'unpaid_deductions' => $unpaid,
                'blocked' => false,
                'loaded' => false,
            ]);
        }
    }
}
