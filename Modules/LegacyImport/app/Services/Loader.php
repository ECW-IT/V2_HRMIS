<?php

namespace Modules\LegacyImport\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Modules\Attendance\Services\EnrolmentService;
use Modules\CoreHr\Enums\AddressType;
use Modules\CoreHr\Enums\ContactType;
use Modules\CoreHr\Enums\EmploymentType;
use Modules\CoreHr\Enums\Gender;
use Modules\CoreHr\Enums\MaritalStatus;
use Modules\CoreHr\Models\DocumentType;
use Modules\CoreHr\Models\Employment;
use Modules\CoreHr\Models\Person;
use Modules\CoreHr\Services\DocumentService;
use Modules\CoreHr\Services\EmployeeNumberGenerator;
use Modules\CoreHr\Services\EmploymentService;
use Modules\CoreHr\Services\PersonService;
use Modules\CoreHr\Support\Nic;
use Modules\LegacyImport\Models\ImportRun;
use Modules\LegacyImport\Models\StgEmployee;
use Modules\LegacyImport\Models\StgIssue;
use Modules\Organisation\Models\Company;
use Modules\Organisation\Models\Grade;
use Modules\Organisation\Models\Job;
use Modules\Organisation\Models\Location;
use Modules\Organisation\Models\OrgUnit;
use RuntimeException;
use Throwable;

final class Loader
{
    /** @var list<array{employee_no: string, name: string, account_no: string}> */
    private array $bankAccountsToComplete = [];

    public function __construct(
        private readonly MappingRepository $maps,
        private readonly PersonService $persons,
        private readonly EmploymentService $employments,
        private readonly EnrolmentService $enrolments,
        private readonly DocumentService $documents,
    ) {}

    /** @return array{run: ImportRun, loaded: int, failed: int, bank_accounts: list<array{employee_no: string, name: string, account_no: string}>} */
    public function run(?int $runId = null, bool $dryRun = false): array
    {
        $this->maps->forget();
        $this->bankAccountsToComplete = [];

        $run = $this->resolveRun($runId);

        $errorCount = StgIssue::query()
            ->where('import_run_id', $run->id)
            ->where('severity', 'ERROR')
            ->count();

        if ($errorCount > 0) {
            throw new RuntimeException('Load refused while ERROR issues exist.');
        }

        if (Employment::withoutGlobalScopes()->exists()) {
            throw new RuntimeException('Load needs an empty employee master. Run migrate:fresh --seed first.');
        }

        $employees = StgEmployee::query()
            ->where('import_run_id', $run->id)
            ->orderBy('id')
            ->get();

        $loaded = 0;
        $failed = 0;
        $sequencesByCompany = [];

        foreach ($employees as $staged) {
            try {
                $employment = DB::transaction(fn () => $this->loadOne($staged));

                if ($dryRun) {
                    $this->undoLoadedEmployment($employment);
                    $loaded++;

                    continue;
                }

                $companyCode = $staged->company_code ?? '';
                $seq = EmployeeNumberGenerator::sequenceOf((string) $employment->employee_no);
                if ($companyCode !== '' && $seq !== null) {
                    $sequencesByCompany[$companyCode] = max($sequencesByCompany[$companyCode] ?? 0, $seq);
                }

                $staged->update([
                    'loaded_employment_id' => $employment->id,
                    'load_error' => null,
                ]);
                $loaded++;
            } catch (Throwable $e) {
                $failed++;
                if (! $dryRun) {
                    $staged->update([
                        'loaded_employment_id' => null,
                        'load_error' => mb_substr($e->getMessage(), 0, 2000),
                    ]);
                }
            }
        }

        if (! $dryRun) {
            foreach ($sequencesByCompany as $code => $maxSeq) {
                Company::query()->where('code', $code)->update([
                    'next_employee_seq' => $maxSeq + 1,
                ]);
            }

            $run->update([
                'stage' => 'LOADED',
                'finished_at' => now(),
                'summary' => array_merge($run->summary ?? [], [
                    'loaded' => $loaded,
                    'failed' => $failed,
                    'bank_accounts_to_complete' => $this->bankAccountsToComplete,
                ]),
            ]);
        }

        return [
            'run' => $run->fresh(),
            'loaded' => $loaded,
            'failed' => $failed,
            'bank_accounts' => $this->bankAccountsToComplete,
        ];
    }

    private function loadOne(StgEmployee $staged): Employment
    {
        $raw = $staged->raw ?? [];
        $nic = Nic::normalise((string) ($staged->normalised_nic ?: ($raw['emp_national_id'] ?? '')));
        $dobFromNic = Nic::dateOfBirth($nic);
        $genderFromNic = Nic::gender($nic);

        $legacyDob = (string) ($raw['emp_birthday'] ?? '');
        $legacyGender = (string) ($raw['emp_gender'] ?? '');
        $expectedGenderLabel = $genderFromNic === Gender::MALE ? 'Male' : 'Female';
        $mismatch = ($legacyDob !== '' && $legacyDob !== $dobFromNic->toDateString())
            || ($legacyGender !== '' && strcasecmp($legacyGender, $expectedGenderLabel) !== 0);

        $lastName = trim((string) ($raw['emp_last_name'] ?? ''));
        if ($lastName === '') {
            $parts = preg_split('/\s+/', trim((string) ($raw['emp_name_with_initial'] ?? ''))) ?: [];
            $lastName = (string) (end($parts) ?: 'Unknown');
        }

        $personData = [
            'nic' => $nic,
            'first_name' => trim((string) ($raw['emp_first_name'] ?? '')) ?: 'Unknown',
            'middle_name' => ($raw['emp_med_name'] ?? null) !== null && trim((string) $raw['emp_med_name']) !== ''
                ? trim((string) $raw['emp_med_name'])
                : null,
            'last_name' => $lastName,
            'name_with_initials' => trim((string) ($raw['emp_name_with_initial'] ?? '')) ?: 'Unknown',
            'calling_name' => ($raw['calling_name'] ?? null) !== null && trim((string) $raw['calling_name']) !== ''
                ? trim((string) $raw['calling_name'])
                : null,
            'date_of_birth' => $dobFromNic->toDateString(),
            'gender' => $genderFromNic->value,
            'marital_status' => $this->mapMarital($raw['emp_marital_status'] ?? null),
            'contacts' => $this->contacts($raw),
            'addresses' => $this->addresses($raw),
        ];

        $person = $this->persons->create($personData);

        if ($mismatch) {
            $person->forceFill(['nic_mismatch' => true])->save();
        }

        foreach ($raw['emergency_contacts'] ?? [] as $contact) {
            $person->emergencyContacts()->create([
                'name' => (string) ($contact['name'] ?? ''),
                'relationship' => (string) ($contact['relationship'] ?? ''),
                'phone' => (string) ($contact['contact_no'] ?? ''),
                'address' => $contact['address'] ?? null,
            ]);
        }

        $company = Company::query()->where('code', $staged->company_code)->firstOrFail();
        $type = match ((int) ($raw['emp_status'] ?? 0)) {
            1 => EmploymentType::PERMANENT,
            2 => EmploymentType::PROBATION,
            3 => EmploymentType::INTERN,
            default => throw new RuntimeException('Unknown employment status.'),
        };

        $hrmEpf = $this->usableEpf($raw['emp_etfno'] ?? null);
        $payEpf = $this->usableEpf($raw['payroll']['epf_no'] ?? null);
        $epfNo = $hrmEpf ?? $payEpf;
        $epfLiable = $epfNo !== null && $type !== EmploymentType::INTERN;

        $hireDate = (string) $raw['emp_join_date'];
        $employmentPayload = [
            'type' => $type->value,
            'hire_date' => $hireDate,
            'employee_no' => $staged->normalised_employee_no,
            'legacy_employee_no' => (string) ($raw['emp_id'] ?? ''),
            'epf_liable' => $epfLiable,
            'epf_no' => $epfNo,
        ];

        if ($type === EmploymentType::PERMANENT && ! empty($raw['emp_permanent_date'])) {
            $employmentPayload['confirmed_on'] = (string) $raw['emp_permanent_date'];
        }

        $orgCode = $this->maps->orgUnitCode(
            $raw['emp_department'] ?? null,
            $raw['section_id'] ?? null,
            $staged->company_code
        );
        $jobCode = $this->maps->jobCodeForTitle($raw['job_title_id'] ?? null);
        $gradeCode = $this->maps->gradeCodeForEmployee($staged->legacy_hrm_id);
        $locationCode = $this->maps->locationCodeForBranch($staged->legacy_branch_id);

        $orgUnit = OrgUnit::query()
            ->where('company_id', $company->id)
            ->where('code', $orgCode)
            ->firstOrFail();
        $job = Job::query()->where('code', $jobCode)->firstOrFail();
        $grade = Grade::query()->where('code', $gradeCode)->firstOrFail();
        $location = Location::query()->where('code', $locationCode)->firstOrFail();

        $employment = $this->employments->hire($person, $company, $employmentPayload, [
            'org_unit_id' => $orgUnit->id,
            'job_id' => $job->id,
            'grade_id' => $grade->id,
            'location_id' => $location->id,
        ]);

        $fp = (int) ($raw['emp_fp_id'] ?? 0);
        if ($fp > 0) {
            $deviceGroup = $this->maps->deviceGroupForBranch($staged->legacy_branch_id);
            if ($deviceGroup !== null) {
                $this->enrolments->enrol($employment, $deviceGroup, $fp, $hireDate);
            }
        }

        $this->loadDocuments($person, $employment, $raw);
        $this->noteBankAccount($employment, $person, $raw);

        return $employment;
    }

    /** @param  array<string, mixed>  $raw */
    private function loadDocuments(Person $person, Employment $employment, array $raw): void
    {
        $base = (string) config('legacy_import.attachment_path');
        foreach ($raw['attachments'] ?? [] as $att) {
            if ((int) ($att['status'] ?? 0) !== 1) {
                continue;
            }
            $fileName = (string) ($att['emp_ath_file_name'] ?? '');
            if ($fileName === '') {
                continue;
            }
            $path = $base.DIRECTORY_SEPARATOR.$fileName;
            if (! is_file($path)) {
                continue;
            }

            $typeCode = $this->maps->documentTypeCode($att['attachment_type'] ?? null) ?? 'OTHER';
            $type = DocumentType::query()->where('code', $typeCode)->first()
                ?? DocumentType::query()->where('code', 'OTHER')->firstOrFail();

            $uploaded = new UploadedFile($path, $fileName, null, null, true);
            $this->documents->upload($person, $uploaded, [
                'document_type_id' => $type->id,
                'employment_id' => $employment->id,
            ], null);
        }
    }

    /** @param  array<string, mixed>  $raw */
    private function noteBankAccount(Employment $employment, Person $person, array $raw): void
    {
        $account = preg_replace('/\D+/', '', (string) ($raw['payroll']['bank_account'] ?? '')) ?? '';
        $len = strlen($account);
        if ($len < 6 || $len > 20) {
            return;
        }

        $this->bankAccountsToComplete[] = [
            'employee_no' => $employment->employee_no,
            'name' => $person->name_with_initials,
            'account_no' => $account,
        ];
    }

    /** @param  array<string, mixed>  $raw
     *  @return list<array<string, mixed>> */
    private function contacts(array $raw): array
    {
        $out = [];
        $mobile = preg_replace('/\D+/', '', (string) ($raw['emp_mobile'] ?? '')) ?? '';
        if (strlen($mobile) >= 9) {
            $out[] = ['type' => ContactType::MOBILE->value, 'value' => $mobile, 'is_primary' => true];
        }
        $email = trim((string) ($raw['emp_email'] ?? ''));
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $out[] = ['type' => ContactType::EMAIL->value, 'value' => $email, 'is_primary' => true];
        }

        return $out;
    }

    /** @param  array<string, mixed>  $raw
     *  @return list<array<string, mixed>> */
    private function addresses(array $raw): array
    {
        $line1 = trim((string) ($raw['emp_address'] ?? ''));
        if ($line1 === '') {
            return [];
        }

        $city = trim((string) ($raw['emp_city'] ?? ''));

        return [[
            'type' => AddressType::PERMANENT->value,
            'line1' => $line1,
            'line2' => ($raw['emp_address_2'] ?? null) !== null && trim((string) $raw['emp_address_2']) !== ''
                ? trim((string) $raw['emp_address_2'])
                : null,
            'city' => $city !== '' ? $city : '-',
        ]];
    }

    private function mapMarital(mixed $value): ?string
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }
        $key = strtoupper(trim((string) $value));

        return match ($key) {
            'SINGLE' => MaritalStatus::SINGLE->value,
            'MARRIED' => MaritalStatus::MARRIED->value,
            'DIVORCED' => MaritalStatus::DIVORCED->value,
            'WIDOWED' => MaritalStatus::WIDOWED->value,
            default => null,
        };
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

    /**
     * Remove a just-loaded employment graph so --dry-run leaves no master data.
     * Query-builder deletes are used because nested service transactions cannot
     * reliably roll back under the test-suite wrapping transaction.
     */
    private function undoLoadedEmployment(Employment $employment): void
    {
        $personId = (int) $employment->person_id;
        $employmentId = (int) $employment->id;

        DB::table('biometric_enrolments')->where('employment_id', $employmentId)->delete();
        DB::table('employee_documents')->where('employment_id', $employmentId)->delete();
        DB::table('employment_events')->where('employment_id', $employmentId)->delete();
        DB::table('assignments')->where('employment_id', $employmentId)->delete();
        DB::table('employments')->where('id', $employmentId)->delete();

        DB::table('person_contacts')->where('person_id', $personId)->delete();
        DB::table('person_addresses')->where('person_id', $personId)->delete();
        DB::table('person_emergency_contacts')->where('person_id', $personId)->delete();
        DB::table('employee_documents')->where('person_id', $personId)->delete();
        DB::table('persons')->where('id', $personId)->delete();
    }

    private function resolveRun(?int $runId): ImportRun
    {
        if ($runId !== null) {
            return ImportRun::query()->findOrFail($runId);
        }

        return ImportRun::query()->orderByDesc('id')->firstOrFail();
    }
}
