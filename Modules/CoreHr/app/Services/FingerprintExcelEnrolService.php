<?php

namespace Modules\CoreHr\Services;

use App\Exceptions\Domain\DomainException;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Attendance\Models\BiometricEnrolment;
use Modules\Attendance\Models\DeviceGroup;
use Modules\Attendance\Services\EnrolmentService;
use Modules\CoreHr\Enums\EmploymentStatus;
use Modules\CoreHr\Models\Employment;
use Modules\Organisation\Models\Company;
use PhpOffice\PhpSpreadsheet\Shared\Date;

final class FingerprintExcelEnrolService
{
    public function __construct(
        private readonly EnrolmentService $enrolments,
        private readonly EmploymentService $employments,
    ) {}

    /**
     * @param  list<array{row: int, data: array<string, string>}>  $rows
     * @return array{success_count: int, error_count: int, errors: list<string>, enrolled: list<string>}
     */
    public function process(Company $company, array $rows): array
    {
        $successCount = 0;
        $errorCount = 0;
        $errors = [];
        $enrolled = [];

        foreach ($rows as $entry) {
            $rowNumber = $entry['row'];
            $data = $entry['data'];

            try {
                $created = false;

                DB::transaction(function () use ($company, $data, &$created): void {
                    $employeeNo = trim($data['employee_no'] ?? '');
                    if ($employeeNo === '') {
                        throw new \InvalidArgumentException('Employee number is required.');
                    }

                    $employment = Employment::query()
                        ->where('company_id', $company->id)
                        ->where('employee_no', $employeeNo)
                        ->first();

                    if ($employment === null) {
                        throw new \InvalidArgumentException('Unknown employee number.');
                    }

                    if ($employment->status === EmploymentStatus::EXITED) {
                        throw new \InvalidArgumentException('Employee has exited.');
                    }

                    $deviceGroup = trim($data['device_group'] ?? '');
                    if ($deviceGroup === '') {
                        throw new \InvalidArgumentException('Device group is required.');
                    }

                    $group = DeviceGroup::query()
                        ->where('is_active', true)
                        ->where('code', $deviceGroup)
                        ->first();

                    if ($group === null) {
                        throw new \InvalidArgumentException("Device group '{$deviceGroup}' is invalid.");
                    }

                    $fingerprintId = trim($data['fingerprint_id'] ?? '');
                    if ($fingerprintId === '' || ! ctype_digit($fingerprintId) || (int) $fingerprintId < 1) {
                        throw new \InvalidArgumentException('Fingerprint ID must be 1 or more.');
                    }

                    $from = $this->validFrom($data['valid_from'] ?? '', $employment);

                    $alreadyOpen = BiometricEnrolment::query()
                        ->where('employment_id', $employment->id)
                        ->where('device_group', $deviceGroup)
                        ->where('device_user_id', (int) $fingerprintId)
                        ->whereNull('valid_to')
                        ->exists();

                    if ($alreadyOpen) {
                        return;
                    }

                    $this->enrolments->enrol($employment, $deviceGroup, (int) $fingerprintId, $from);

                    if (! $employment->attendance_active) {
                        $this->employments->updateModuleFlags($employment, ['attendance_active' => true], $from);
                    }

                    $created = true;
                });

                $successCount++;
                if ($created) {
                    $enrolled[] = trim($data['employee_no'] ?? '');
                }
            } catch (DomainException|\InvalidArgumentException $e) {
                $errorCount++;
                $errors[] = "Row {$rowNumber}: ".$e->getMessage();
            } catch (\Throwable $e) {
                $errorCount++;
                $errors[] = "Row {$rowNumber}: ".$e->getMessage();
            }
        }

        return [
            'success_count' => $successCount,
            'error_count' => $errorCount,
            'errors' => $errors,
            'enrolled' => $enrolled,
        ];
    }

    private function validFrom(string $value, Employment $employment): string
    {
        $hireDate = $employment->hire_date->toDateString();
        $today = CarbonImmutable::now(config('app.timezone'))->toDateString();

        if (trim($value) === '') {
            return max($today, $hireDate);
        }

        return $this->normalizeDate($value);
    }

    private function normalizeDate(string $value): string
    {
        if (is_numeric($value)) {
            try {
                return Date::excelToDateTimeObject((float) $value)->format('Y-m-d');
            } catch (\Throwable) {
                throw new \InvalidArgumentException('Valid from is not a valid date.');
            }
        }

        $timestamp = strtotime($value);
        if ($timestamp === false) {
            throw new \InvalidArgumentException('Valid from is not a valid date.');
        }

        return date('Y-m-d', $timestamp);
    }
}
