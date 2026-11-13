<?php

namespace Modules\LegacyImport\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\CoreHr\Exceptions\InvalidNic;
use Modules\CoreHr\Services\EmployeeNumberGenerator;
use Modules\CoreHr\Support\Nic;
use Modules\LegacyImport\Models\ImportRun;
use Modules\LegacyImport\Models\StgEmployee;
use Modules\LegacyImport\Models\StgIssue;
use Modules\Organisation\Models\Grade;

final class Validator
{
    public function __construct(private readonly MappingRepository $maps) {}

    /** @return array{run: ImportRun, counts: array<string, int>, errors: int} */
    public function run(?int $runId = null): array
    {
        $this->maps->forget();
        $run = $this->resolveRun($runId);

        StgIssue::query()->where('import_run_id', $run->id)->delete();

        $employees = StgEmployee::query()
            ->where('import_run_id', $run->id)
            ->orderBy('id')
            ->get();

        $issues = [];

        $nicGroups = $employees->groupBy(fn (StgEmployee $e) => $e->normalised_nic);
        foreach ($nicGroups as $nic => $group) {
            if ($nic === null || $nic === '' || $group->count() < 2) {
                continue;
            }
            foreach ($group as $e) {
                $issues[] = $this->issue($run->id, $e->legacy_hrm_id, 'IMP001', 'ERROR', 'Duplicate normalised NIC.');
            }
        }

        $empNoGroups = $employees->groupBy(fn (StgEmployee $e) => ($e->company_code ?? '').'|'.($e->normalised_employee_no ?? ''));
        foreach ($empNoGroups as $key => $group) {
            if (! str_contains((string) $key, '|') || str_ends_with((string) $key, '|') || $group->count() < 2) {
                continue;
            }
            [$company] = explode('|', (string) $key, 2);
            if ($company === '') {
                continue;
            }
            foreach ($group as $e) {
                $issues[] = $this->issue($run->id, $e->legacy_hrm_id, 'IMP004', 'ERROR', 'Duplicate employee number in company.');
            }
        }

        $fpGroups = [];
        foreach ($employees as $e) {
            $fp = (int) ($e->raw['emp_fp_id'] ?? 0);
            if ($fp <= 0) {
                continue;
            }
            $group = $this->maps->deviceGroupForBranch($e->legacy_branch_id);
            if ($group === null) {
                continue;
            }
            $fpGroups[$group.'|'.$fp][] = $e;
        }
        foreach ($fpGroups as $group) {
            if (count($group) < 2) {
                continue;
            }
            foreach ($group as $e) {
                $issues[] = $this->issue($run->id, $e->legacy_hrm_id, 'IMP013', 'ERROR', 'Shared fingerprint ID in device group.');
            }
        }

        foreach ($employees as $e) {
            $raw = $e->raw ?? [];
            $issues = array_merge($issues, $this->validateEmployee($run->id, $e, $raw));
        }

        $issues = array_merge($issues, $this->validateOrphanPayroll($run->id, $employees));

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

        ksort($counts);

        $errors = (int) StgIssue::query()
            ->where('import_run_id', $run->id)
            ->where('severity', 'ERROR')
            ->count();

        $run->update([
            'stage' => 'VALIDATED',
            'summary' => array_merge($run->summary ?? [], [
                'issue_counts' => $counts,
                'error_count' => $errors,
            ]),
        ]);

        return [
            'run' => $run->fresh(),
            'counts' => $counts,
            'errors' => $errors,
        ];
    }

    /**
     * @param  array<string, mixed>  $raw
     * @return list<array<string, mixed>>
     */
    private function validateEmployee(int $runId, StgEmployee $e, array $raw): array
    {
        $out = [];
        $legacyId = $e->legacy_hrm_id;

        $nicRaw = trim((string) ($raw['emp_national_id'] ?? ''));
        if ($nicRaw === '') {
            $out[] = $this->issue($runId, $legacyId, 'IMP016', 'ERROR', 'NIC is empty.');
        } else {
            try {
                $nic = Nic::normalise($nicRaw);
                $dob = $raw['emp_birthday'] ?? null;
                $gender = $raw['emp_gender'] ?? null;
                $expectedDob = Nic::dateOfBirth($nic)->toDateString();
                $expectedGender = Nic::gender($nic)->value === 'MALE' ? 'Male' : 'Female';
                $dobStr = $dob ? (string) $dob : '';
                $genderStr = is_string($gender) ? $gender : '';
                if ($dobStr !== '' && $dobStr !== $expectedDob) {
                    $out[] = $this->issue($runId, $legacyId, 'IMP002', 'WARNING', 'Date of birth disagrees with NIC.');
                } elseif ($genderStr !== '' && strcasecmp($genderStr, $expectedGender) !== 0) {
                    $out[] = $this->issue($runId, $legacyId, 'IMP002', 'WARNING', 'Gender disagrees with NIC.');
                }
            } catch (InvalidNic) {
                $out[] = $this->issue($runId, $legacyId, 'IMP016', 'ERROR', 'NIC is not valid.');
            }
        }

        if ($this->maps->companyCodeForBranch($e->legacy_branch_id) === null) {
            $out[] = $this->issue($runId, $legacyId, 'IMP003', 'ERROR', 'Branch has no company_map row.');
        }

        if (($raw['emp_join_date'] ?? null) === null || $raw['emp_join_date'] === '') {
            $out[] = $this->issue($runId, $legacyId, 'IMP005', 'ERROR', 'Join date is empty.');
        }

        $status = $raw['emp_status'] ?? null;
        if (! in_array((int) $status, [1, 2, 3], true)) {
            $out[] = $this->issue($runId, $legacyId, 'IMP006', 'ERROR', 'Unknown employment status.');
        }

        $hrmEpf = $this->usableEpf($raw['emp_etfno'] ?? null);
        $payEpf = $this->usableEpf($raw['payroll']['epf_no'] ?? null);
        $isIntern = (int) $status === 3;

        if (! $isIntern && $hrmEpf === null && $payEpf === null) {
            $out[] = $this->issue($runId, $legacyId, 'IMP007', 'WARNING', 'No usable EPF number.');
        }

        if ($hrmEpf !== null && $payEpf !== null && $hrmEpf !== $payEpf) {
            $out[] = $this->issue($runId, $legacyId, 'IMP008', 'ERROR', 'HRM and payroll EPF numbers differ.');
        }

        $orgCode = $this->maps->orgUnitCode(
            $raw['emp_department'] ?? null,
            $raw['section_id'] ?? null,
            $e->company_code
        );
        if ($orgCode === null) {
            $out[] = $this->issue($runId, $legacyId, 'IMP009', 'ERROR', 'No org_unit_map row for department/section.');
        }

        if ($this->maps->jobCodeForTitle($raw['job_title_id'] ?? null) === null) {
            $out[] = $this->issue($runId, $legacyId, 'IMP010', 'ERROR', 'No job_map row for job title.');
        }

        $gradeCode = $this->maps->gradeCodeForEmployee($legacyId);
        if ($gradeCode === null || ! Grade::query()->where('code', $gradeCode)->exists()) {
            $out[] = $this->issue($runId, $legacyId, 'IMP011', 'ERROR', 'Missing or unknown grade assignment.');
        }

        $fp = (int) ($raw['emp_fp_id'] ?? 0);
        if ($fp <= 0) {
            $out[] = $this->issue($runId, $legacyId, 'IMP012', 'WARNING', 'Fingerprint ID is empty or zero.');
        }

        $attachmentPath = (string) config('legacy_import.attachment_path');
        foreach ($raw['attachments'] ?? [] as $att) {
            $file = (string) ($att['emp_ath_file_name'] ?? '');
            if ($file === '') {
                continue;
            }
            if (! is_file($attachmentPath.DIRECTORY_SEPARATOR.$file)) {
                $out[] = $this->issue($runId, $legacyId, 'IMP014', 'WARNING', "Attachment file not found: {$file}");
            }
        }

        $payroll = $raw['payroll'] ?? null;
        $payrollActive = is_array($payroll) && (int) ($payroll['status'] ?? 0) === 1;
        if (! $payrollActive) {
            $out[] = $this->issue($runId, $legacyId, 'IMP015', 'ERROR', 'No active payroll profile in the same company.');
        }

        $mobile = preg_replace('/\D+/', '', (string) ($raw['emp_mobile'] ?? '')) ?? '';
        if ($mobile === '') {
            $out[] = $this->issue($runId, $legacyId, 'IMP018', 'WARNING', 'No mobile number.');
        }

        return $out;
    }

    /**
     * @param  Collection<int, StgEmployee>  $employees
     * @return list<array<string, mixed>>
     */
    private function validateOrphanPayroll(int $runId, $employees): array
    {
        $matched = [];
        foreach ($employees as $e) {
            $payroll = $e->raw['payroll'] ?? null;
            if (! is_array($payroll) || (int) ($payroll['status'] ?? 0) !== 1) {
                continue;
            }
            $empNo = (string) ($payroll['emp_no'] ?? '');
            if ($empNo === '' || $e->company_code === null) {
                continue;
            }
            $matched[$e->company_code.'|'.EmployeeNumberGenerator::normalise($empNo)] = true;
        }

        $out = [];
        $profiles = DB::connection('legacy_payroll')
            ->table('payr__employee_profile')
            ->where('status', 1)
            ->get();

        foreach ($profiles as $profile) {
            $p = (array) $profile;
            $company = $this->maps->companyCodeForBranch($p['branch_id'] ?? null);
            $empNo = (string) ($p['emp_no'] ?? '');
            if ($company === null || $empNo === '') {
                continue;
            }
            $key = $company.'|'.EmployeeNumberGenerator::normalise($empNo);
            if (! isset($matched[$key])) {
                $out[] = $this->issue($runId, null, 'IMP017', 'ERROR', "Active payroll profile has no active HRM employee: {$empNo}");
            }
        }

        return $out;
    }

    private function usableEpf(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $trimmed = trim((string) $value);
        if ($trimmed === '' || in_array($trimmed, ['.', '-', '0'], true)) {
            return null;
        }

        return $trimmed;
    }

    /** @return array{import_run_id: int, legacy_hrm_id: ?int, code: string, severity: string, message: string} */
    private function issue(int $runId, ?int $legacyId, string $code, string $severity, string $message): array
    {
        return [
            'import_run_id' => $runId,
            'legacy_hrm_id' => $legacyId,
            'code' => $code,
            'severity' => $severity,
            'message' => mb_substr($message, 0, 500),
        ];
    }

    private function resolveRun(?int $runId): ImportRun
    {
        if ($runId !== null) {
            return ImportRun::query()->findOrFail($runId);
        }

        return ImportRun::query()->orderByDesc('id')->firstOrFail();
    }
}
