<?php

namespace Modules\LegacyImport\Services;

use Illuminate\Support\Facades\DB;
use Modules\CoreHr\Exceptions\InvalidNic;
use Modules\CoreHr\Services\EmployeeNumberGenerator;
use Modules\CoreHr\Support\Nic;
use Modules\LegacyImport\Models\ImportRun;
use Modules\LegacyImport\Models\StgEmployee;
use Modules\Organisation\Models\Company;
use Modules\Organisation\Models\Job;
use Modules\Organisation\Models\OrgUnit;
use Modules\Organisation\Services\JobService;
use Modules\Organisation\Services\OrgUnitService;

final class Stager
{
    public function __construct(
        private readonly MappingRepository $maps,
        private readonly OrgUnitService $orgUnits,
        private readonly JobService $jobs,
    ) {}

    /** @return array{run: ImportRun, staged: int, legacy_active: int} */
    public function run(?int $runBy = null): array
    {
        $this->maps->forget();
        $this->ensureCatalogue();

        $legacyActive = DB::connection('legacy_hrm')
            ->table('employees')
            ->where('is_resigned', 0)
            ->where('deleted', 0)
            ->count();

        $run = ImportRun::query()->create([
            'started_at' => now(),
            'finished_at' => null,
            'stage' => 'STAGED',
            'summary' => null,
            'run_by' => $runBy,
        ]);

        $employees = DB::connection('legacy_hrm')
            ->table('employees')
            ->where('is_resigned', 0)
            ->where('deleted', 0)
            ->orderBy('id')
            ->get();

        $payrollByKey = $this->payrollIndex();

        $sections = DB::connection('legacy_hrm')
            ->table('section_employees')
            ->where('status', 'active')
            ->get()
            ->groupBy('employee_id');

        $emergencies = DB::connection('legacy_hrm')
            ->table('emergency_contacts')
            ->where('status', 1)
            ->get()
            ->groupBy('emp_id');

        $attachments = DB::connection('legacy_hrm')
            ->table('employee_attachments')
            ->where('status', 1)
            ->get()
            ->groupBy('emp_id');

        $staged = 0;

        foreach ($employees as $row) {
            $rowArray = (array) $row;
            $branchId = $rowArray['emp_branch'] ?? null;
            $companyCode = $this->maps->companyCodeForBranch($branchId);
            $empId = (string) ($rowArray['emp_id'] ?? '');
            $normalisedEmpNo = $empId !== '' ? EmployeeNumberGenerator::normalise($empId) : null;

            $payroll = null;
            if ($companyCode !== null && $normalisedEmpNo !== null) {
                $payroll = $payrollByKey[$companyCode.'|'.$normalisedEmpNo] ?? null;
            }

            $sectionId = null;
            $sectionRows = $sections->get($rowArray['id']) ?? collect();
            if ($sectionRows->isNotEmpty()) {
                $sectionId = (int) $sectionRows->first()->section_id;
            }

            $raw = $rowArray;
            $raw['section_id'] = $sectionId;
            $raw['payroll'] = $payroll;
            $raw['emergency_contacts'] = ($emergencies->get($rowArray['id']) ?? collect())
                ->map(fn ($c) => (array) $c)
                ->values()
                ->all();
            $raw['attachments'] = ($attachments->get($rowArray['id']) ?? collect())
                ->map(fn ($a) => (array) $a)
                ->values()
                ->all();

            $nicRaw = trim((string) ($rowArray['emp_national_id'] ?? ''));
            $normalisedNic = null;
            if ($nicRaw !== '') {
                try {
                    $normalisedNic = Nic::normalise($nicRaw);
                } catch (InvalidNic) {
                    $normalisedNic = null;
                }
            }

            StgEmployee::query()->create([
                'import_run_id' => $run->id,
                'legacy_hrm_id' => (int) $rowArray['id'],
                'legacy_branch_id' => $branchId !== null ? (int) $branchId : null,
                'raw' => $raw,
                'normalised_employee_no' => $normalisedEmpNo,
                'normalised_nic' => $normalisedNic,
                'company_code' => $companyCode,
                'loaded_employment_id' => null,
                'load_error' => null,
            ]);

            $staged++;
        }

        $run->update([
            'summary' => [
                'legacy_active' => $legacyActive,
                'staged' => $staged,
            ],
        ]);

        return [
            'run' => $run->fresh(),
            'staged' => $staged,
            'legacy_active' => $legacyActive,
        ];
    }

    private function ensureCatalogue(): void
    {
        foreach ($this->maps->rows('jobs') as $row) {
            $exists = Job::query()->where('code', $row['code'])->exists();
            if (! $exists) {
                $this->jobs->create([
                    'code' => $row['code'],
                    'name' => $row['name'],
                ]);
            }
        }

        $companies = Company::query()->get()->keyBy('code');
        $created = [];

        foreach ($this->maps->rows('org_units') as $row) {
            $company = $companies->get($row['company_code']);
            if ($company === null) {
                continue;
            }

            $exists = OrgUnit::query()
                ->where('company_id', $company->id)
                ->where('code', $row['code'])
                ->exists();

            if ($exists) {
                $created[$row['company_code'].'|'.$row['code']] = OrgUnit::query()
                    ->where('company_id', $company->id)
                    ->where('code', $row['code'])
                    ->first();

                continue;
            }

            $parentId = null;
            $parentCode = trim((string) ($row['parent_code'] ?? ''));
            if ($parentCode !== '') {
                $parent = $created[$row['company_code'].'|'.$parentCode]
                    ?? OrgUnit::query()
                        ->where('company_id', $company->id)
                        ->where('code', $parentCode)
                        ->first();
                $parentId = $parent?->id;
            }

            $unit = $this->orgUnits->create([
                'company_id' => $company->id,
                'parent_id' => $parentId,
                'code' => $row['code'],
                'name' => $row['name'],
                'type' => $row['type'],
            ]);

            $created[$row['company_code'].'|'.$row['code']] = $unit;
        }
    }

    /** @return array<string, array{emp_no: mixed, epf_no: mixed, bank_account: mixed, status: mixed}> */
    private function payrollIndex(): array
    {
        $index = [];

        $profiles = DB::connection('legacy_payroll')
            ->table('payr__employee_profile')
            ->get();

        foreach ($profiles as $profile) {
            $p = (array) $profile;
            $companyCode = $this->maps->companyCodeForBranch($p['branch_id'] ?? null);
            $empNo = (string) ($p['emp_no'] ?? '');
            if ($companyCode === null || $empNo === '') {
                continue;
            }

            $key = $companyCode.'|'.EmployeeNumberGenerator::normalise($empNo);
            $index[$key] = [
                'emp_no' => $p['emp_no'] ?? null,
                'epf_no' => $p['epf_no'] ?? null,
                'bank_account' => $p['bank_account'] ?? null,
                'status' => $p['status'] ?? null,
            ];
        }

        return $index;
    }
}
