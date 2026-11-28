<?php

namespace Modules\CoreHr\Services;

use App\Exceptions\Domain\DomainException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Modules\Attendance\Enums\AttendanceMode;
use Modules\Attendance\Enums\OtMode;
use Modules\Attendance\Models\DeviceGroup;
use Modules\Attendance\Models\Shift;
use Modules\Attendance\Services\EnrolmentService;
use Modules\Attendance\Services\TimeProfileService;
use Modules\CoreHr\Enums\ArriveWorkFrom;
use Modules\CoreHr\Enums\EmploymentStatus;
use Modules\CoreHr\Enums\EmploymentType;
use Modules\CoreHr\Enums\ExitReason;
use Modules\CoreHr\Enums\Gender;
use Modules\CoreHr\Enums\MaritalStatus;
use Modules\CoreHr\Exceptions\InvalidNic;
use Modules\CoreHr\Models\Bank;
use Modules\CoreHr\Models\BankBranch;
use Modules\CoreHr\Models\Employment;
use Modules\CoreHr\Models\Person;
use Modules\CoreHr\Models\Region;
use Modules\CoreHr\Support\Nic;
use Modules\CoreHr\Support\SriLankaGeo;
use Modules\Organisation\Models\Company;
use Modules\Organisation\Models\Designation;
use Modules\Organisation\Models\EmployeeCategory;
use Modules\Organisation\Models\Location;
use Modules\Organisation\Models\OrgUnit;
use Modules\Payroll\Enums\OtRateMode;
use Modules\Payroll\Services\CompensationService;
use Modules\Payroll\Services\PayRuleService;
use PhpOffice\PhpSpreadsheet\Shared\Date;

final class EmployeeExcelHireService
{
    public function __construct(
        private readonly PersonService $persons,
        private readonly EmploymentService $employments,
        private readonly BankAccountService $banks,
        private readonly CompensationService $compensation,
        private readonly TimeProfileService $timeProfiles,
        private readonly EnrolmentService $enrolments,
        private readonly PayRuleService $payRules,
    ) {}

    /**
     * @param  list<array{row: int, data: array<string, string>}>  $rows
     * @return array{success_count: int, error_count: int, errors: list<string>, hired: list<string>}
     */
    public function process(Company $company, array $rows): array
    {
        $successCount = 0;
        $errorCount = 0;
        $errors = [];
        $hired = [];

        foreach ($rows as $entry) {
            $rowNumber = $entry['row'];
            try {
                $employment = $this->hireOne($company, $entry['data']);
                $successCount++;
                $hired[] = $employment->employee_no;
            } catch (DomainException|\InvalidArgumentException|InvalidNic $e) {
                $errorCount++;
                $errors[] = "Row {$rowNumber}: ".$e->getMessage();
            } catch (UniqueConstraintViolationException) {
                $errorCount++;
                $errors[] = "Row {$rowNumber}: This person already has an active employment.";
            } catch (\Throwable $e) {
                $errorCount++;
                $errors[] = "Row {$rowNumber}: ".$e->getMessage();
            }
        }

        return [
            'success_count' => $successCount,
            'error_count' => $errorCount,
            'errors' => $errors,
            'hired' => $hired,
        ];
    }

    /** @param  array<string, string>  $data */
    public function hireOne(Company $company, array $data): Employment
    {
        $data = $this->normalizeAliases($data);
        $data = $this->blankPlaceholders($data);
        $data = $this->applyDefaults($data);

        $nic = $data['nic'] ?? '';
        $passport = $data['passport_no'] ?? '';
        $employeeNo = ($data['employee_no'] ?? '') !== '' ? $data['employee_no'] : null;

        $type = $this->normalizeEmploymentType($data['type']);
        if ($type === null) {
            throw new \InvalidArgumentException("Type '{$data['type']}' is invalid.");
        }

        $gender = strtoupper($data['gender']);
        if (Gender::tryFrom($gender) === null) {
            $gender = Gender::MALE->value;
        }

        $hireDate = $this->normalizeDate($data['hire_date'], 'Hire date');
        $dateOfBirth = $this->normalizeDate($data['date_of_birth'], 'Date of birth');

        [$nic, $passport, $dateOfBirth, $gender] = $this->reconcileIdentity(
            $nic,
            $passport,
            $dateOfBirth,
            $gender,
            $employeeNo
        );

        $orgUnit = OrgUnit::query()
            ->where('company_id', $company->id)
            ->where('is_active', true)
            ->where('name', $data['org_unit'])
            ->first();
        if ($orgUnit === null) {
            throw new \InvalidArgumentException("Org unit '{$data['org_unit']}' not found.");
        }

        $designation = $this->resolveDesignation($data['designation'] ?? '');
        if ($designation === null) {
            throw new \InvalidArgumentException(
                ($data['designation'] ?? '') === ''
                    ? 'No active designation is available to use as a default.'
                    : "Designation '{$data['designation']}' not found."
            );
        }

        $location = Location::query()->where('is_active', true)->where('name', $data['location'])->first();
        if ($location === null) {
            throw new \InvalidArgumentException("Location '{$data['location']}' not found.");
        }

        $shift = $this->resolveShift($data['shift'] ?? '');
        if ($shift === null) {
            throw new \InvalidArgumentException(
                ($data['shift'] ?? '') === ''
                    ? 'No active shift is available to use as a default.'
                    : "Shift '{$data['shift']}' not found."
            );
        }

        $categoryId = null;
        if (($data['employee_category'] ?? '') !== '') {
            $category = EmployeeCategory::query()
                ->where('is_active', true)
                ->where('name', $data['employee_category'])
                ->first();
            if ($category === null) {
                $category = EmployeeCategory::query()
                    ->where('is_active', true)
                    ->whereRaw('LOWER(name) = ?', [mb_strtolower($data['employee_category'])])
                    ->first();
            }
            if ($category === null) {
                throw new \InvalidArgumentException("Category '{$data['employee_category']}' not found.");
            }
            $categoryId = $category->id;
        }

        $isCash = $this->parseYesNo($data['paid_in_cash'], 'Paid in cash');
        $bankData = ['effective_from' => $hireDate, 'is_cash' => true];
        if (! $isCash) {
            foreach (['bank' => 'Bank', 'branch' => 'Branch', 'account_no' => 'Account no.', 'account_name' => 'Account name'] as $key => $label) {
                if (($data[$key] ?? '') === '') {
                    throw new \InvalidArgumentException("{$label} is required when paid in cash is No.");
                }
            }
            $bank = Bank::query()->where('is_active', true)->where('name', $data['bank'])->first();
            if ($bank === null) {
                throw new \InvalidArgumentException("Bank '{$data['bank']}' not found.");
            }
            $branch = BankBranch::query()
                ->where('is_active', true)
                ->where('bank_id', $bank->id)
                ->where('name', $data['branch'])
                ->first();
            if ($branch === null) {
                throw new \InvalidArgumentException("Branch '{$data['branch']}' not found for bank '{$data['bank']}'.");
            }
            $bankData = [
                'effective_from' => $hireDate,
                'is_cash' => false,
                'bank_id' => $bank->id,
                'bank_branch_id' => $branch->id,
                'account_no' => $data['account_no'],
                'account_name' => $data['account_name'] !== '' ? $data['account_name'] : $data['name_with_initials'],
            ];
        }

        $managerId = null;
        if (($data['reports_to_employee_no'] ?? '') !== '') {
            $manager = Employment::query()
                ->where('company_id', $company->id)
                ->where('employee_no', $data['reports_to_employee_no'])
                ->where('status', '!=', EmploymentStatus::EXITED)
                ->first();
            if ($manager === null) {
                throw new \InvalidArgumentException("Reports-to employee '{$data['reports_to_employee_no']}' not found.");
            }
            $managerId = $manager->id;
        }

        $otMode = $this->normalizeOtMode($data['ot_mode'] ?? '');
        if ($otMode === null) {
            throw new \InvalidArgumentException("OT mode '{$data['ot_mode']}' is invalid.");
        }

        $lateDeduction = ($data['late_deduction'] ?? '') === ''
            ? false
            : $this->parseYesNo($data['late_deduction'], 'Late deduction');

        $attendanceActive = ($data['attendance_active'] ?? '') === ''
            ? true
            : $this->parseYesNo($data['attendance_active'], 'Attendance status');

        $hrmisActive = ($data['hrmis_active'] ?? '') === ''
            ? true
            : $this->parseYesNo($data['hrmis_active'], 'HRIS status');

        $payrollActive = ($data['payroll_active'] ?? '') === ''
            ? true
            : $this->parseYesNo($data['payroll_active'], 'Payroll status');

        $hostelAvailable = ($data['hostel_available'] ?? '') === ''
            ? false
            : $this->parseYesNo($data['hostel_available'], 'Hostel status');

        $foodBreakfast = ($data['food_breakfast'] ?? '') === ''
            ? false
            : $this->parseYesNo($data['food_breakfast'], 'Breakfast');

        $foodLunch = ($data['food_lunch'] ?? '') === ''
            ? false
            : $this->parseYesNo($data['food_lunch'], 'Lunch');

        $foodDinner = ($data['food_dinner'] ?? '') === ''
            ? false
            : $this->parseYesNo($data['food_dinner'], 'Dinner');

        $otRateMode = strtoupper($data['ot_rate_mode'] ?? '') ?: OtRateMode::CALCULATED->value;
        if (OtRateMode::tryFrom($otRateMode) === null) {
            $otRateMode = OtRateMode::CALCULATED->value;
        }

        $otRate = null;
        if ($otRateMode === OtRateMode::FIXED->value) {
            $otRateRaw = str_replace(',', '', $data['ot_rate'] ?? '');
            if ($otRateRaw !== '' && is_numeric($otRateRaw) && (float) $otRateRaw > 0) {
                $otRate = $otRateRaw;
            } else {
                // Legacy sheets often mark FIXED with a blank/zero rate — fall back to calculated.
                $otRateMode = OtRateMode::CALCULATED->value;
                $otRate = null;
            }
        }

        $attendanceType = null;
        if (($data['attendance_type'] ?? '') !== '') {
            $tierCode = trim($data['attendance_type']);
            if ($this->payRules->tier($tierCode, $hireDate) !== null) {
                $attendanceType = $tierCode;
            }
        }

        $arriveWorkFrom = $this->normalizeArriveWorkFrom($data['arrive_work_from'] ?? '');
        if (($data['arrive_work_from'] ?? '') !== '' && $arriveWorkFrom === null) {
            throw new \InvalidArgumentException("Arrive from '{$data['arrive_work_from']}' is invalid.");
        }

        if (($data['region'] ?? '') !== '') {
            $validRegions = Region::activeNames();
            if ($validRegions !== [] && ! in_array($data['region'], $validRegions, true)) {
                throw new \InvalidArgumentException("Region '{$data['region']}' is invalid.");
            }
        }

        if (($data['province'] ?? '') !== '' && ! in_array($data['province'], SriLankaGeo::provinces(), true)) {
            throw new \InvalidArgumentException("Province '{$data['province']}' is invalid.");
        }

        if (($data['electorate_district'] ?? '') !== '') {
            $districts = ($data['province'] ?? '') !== ''
                ? SriLankaGeo::districtsForProvince($data['province'])
                : SriLankaGeo::districts();
            if (! in_array($data['electorate_district'], $districts, true)) {
                throw new \InvalidArgumentException("Electorate district '{$data['electorate_district']}' is invalid.");
            }
        }

        $distanceKm = null;
        if (($data['distance_km'] ?? '') !== '') {
            if (! is_numeric($data['distance_km'])) {
                throw new \InvalidArgumentException('Distance must be a number.');
            }
            $distanceKm = $data['distance_km'];
        }

        $deviceGroup = trim($data['device_group'] ?? '');
        $fingerprintId = trim($data['fingerprint_id'] ?? '');
        $canEnrol = false;
        if ($attendanceActive && $deviceGroup !== '') {
            $group = DeviceGroup::query()->where('is_active', true)->where('code', $deviceGroup)->first();
            if ($group !== null && $fingerprintId !== '' && ctype_digit($fingerprintId) && (int) $fingerprintId >= 1) {
                $canEnrol = true;
            } else {
                // Soft import: missing/invalid fingerprint or unknown group → skip enrolment.
                $deviceGroup = '';
                $fingerprintId = '';
            }
        } elseif ($fingerprintId !== '' && $deviceGroup === '') {
            $fingerprintId = '';
        }

        $earningType = trim($data['earning_type'] ?? '');
        $earningAmount = trim($data['earning_amount'] ?? '');
        if (($earningType === '') !== ($earningAmount === '')) {
            throw new \InvalidArgumentException('Earning type and earning amount must be provided together.');
        }

        $deductionType = trim($data['deduction_type'] ?? '');
        $deductionAmount = trim($data['deduction_amount'] ?? '');
        if (($deductionType === '') !== ($deductionAmount === '')) {
            throw new \InvalidArgumentException('Deduction type and deduction amount must be provided together.');
        }

        $resignDate = null;
        if (($data['resign_date'] ?? '') !== '') {
            $resignDate = $this->normalizeDate($data['resign_date'], 'Resign date');
            if ($resignDate < $hireDate) {
                throw new \InvalidArgumentException('Resign date cannot be before the hire date.');
            }
        }

        $epfNo = ($data['epf_no'] ?? '') !== '' ? $data['epf_no'] : null;

        $marital = $this->normalizeMaritalStatus($data['marital_status'] ?? '');

        $existingPerson = null;
        if ($nic !== '') {
            $existingPerson = $this->persons->findByNic($nic);
        } elseif ($passport !== '') {
            $existingPerson = Person::query()->where('passport_no', $passport)->first();
        }

        $mode = 'HIRE';
        if ($existingPerson !== null) {
            $active = Employment::query()
                ->where('person_id', $existingPerson->id)
                ->where('status', '!=', EmploymentStatus::EXITED)
                ->first();
            if ($active !== null) {
                throw new \InvalidArgumentException(
                    "This person is already employed ({$active->employee_no})."
                );
            }

            $hasExitedHere = Employment::query()
                ->where('person_id', $existingPerson->id)
                ->where('company_id', $company->id)
                ->where('status', EmploymentStatus::EXITED)
                ->exists();
            $mode = $hasExitedHere ? 'REJOIN' : 'HIRE';
        }

        $isIntern = $type === EmploymentType::INTERN->value
            || strcasecmp($designation->name, 'intern') === 0
            || strcasecmp((string) $designation->code, 'intern') === 0;

        $confirmationDue = null;
        if ($type === EmploymentType::PROBATION->value && ($data['confirmation_due_date'] ?? '') !== '') {
            $confirmationDue = $this->normalizeDate($data['confirmation_due_date'], 'Confirmation due date');
        }

        $basicSalary = str_replace([',', 'Rs', 'rs', 'RS', ' '], '', $data['basic_salary']);
        if ($basicSalary === '' || ! is_numeric($basicSalary) || (float) $basicSalary <= 0) {
            $basicSalary = '1.00';
        }
        // Excel import always needs a positive compensation basic (intern flag is separate).

        $derivedFullName = trim($data['first_name'].' '.(($data['middle_name'] ?? '') !== '' ? $data['middle_name'].' ' : '').$data['last_name']);
        $fullName = ($data['full_name'] ?? '') !== '' ? trim($data['full_name']) : $derivedFullName;

        $city = trim($data['city'] ?? '');
        if ($city === '') {
            $city = trim($data['district'] ?? '');
        }
        if ($city === '') {
            $city = trim($data['electorate_district'] ?? '');
        }
        if ($city === '') {
            $city = '-';
        }

        return DB::transaction(function () use (
            $company,
            $data,
            $nic,
            $passport,
            $gender,
            $marital,
            $dateOfBirth,
            $hireDate,
            $type,
            $orgUnit,
            $designation,
            $location,
            $shift,
            $categoryId,
            $bankData,
            $managerId,
            $otMode,
            $lateDeduction,
            $attendanceActive,
            $hrmisActive,
            $payrollActive,
            $hostelAvailable,
            $foodBreakfast,
            $foodLunch,
            $foodDinner,
            $otRateMode,
            $otRate,
            $attendanceType,
            $arriveWorkFrom,
            $distanceKm,
            $deviceGroup,
            $fingerprintId,
            $canEnrol,
            $earningType,
            $earningAmount,
            $deductionType,
            $deductionAmount,
            $resignDate,
            $employeeNo,
            $epfNo,
            $existingPerson,
            $mode,
            $isIntern,
            $confirmationDue,
            $basicSalary,
            $fullName,
            $city,
        ) {
            $personData = [
                'title' => null,
                'full_name' => $fullName,
                'first_name' => $data['first_name'],
                'middle_name' => ($data['middle_name'] ?? '') !== '' ? $data['middle_name'] : null,
                'last_name' => $data['last_name'],
                'name_with_initials' => $data['name_with_initials'],
                'calling_name' => $data['calling_name'],
                'date_of_birth' => $dateOfBirth,
                'gender' => $gender,
                'marital_status' => $marital,
                'nationality' => ($data['nationality'] ?? '') !== '' ? $data['nationality'] : 'Sri Lankan',
                'driving_licence_no' => ($data['driving_licence_no'] ?? '') !== '' ? $data['driving_licence_no'] : null,
                'landline_no' => ($data['landline_no'] ?? '') !== '' ? $data['landline_no'] : null,
                'office_no' => ($data['office_no'] ?? '') !== '' ? $data['office_no'] : null,
                'region' => ($data['region'] ?? '') !== '' ? $data['region'] : null,
                'arrive_work_from' => $arriveWorkFrom,
                'province' => ($data['province'] ?? '') !== '' ? $data['province'] : null,
                'electorate_district' => ($data['electorate_district'] ?? '') !== '' ? $data['electorate_district'] : null,
                'gn_division' => ($data['gn_division'] ?? '') !== '' ? $data['gn_division'] : null,
                'distance_km' => $distanceKm,
                'addresses' => [[
                    'type' => 'PERMANENT',
                    'line1' => $data['address_line_1'],
                    'line2' => ($data['address_line_2'] ?? '') !== '' ? $data['address_line_2'] : null,
                    'city' => $city,
                    'district' => ($data['district'] ?? '') !== '' ? $data['district'] : null,
                    'postal_code' => ($data['postal_code'] ?? '') !== '' ? $data['postal_code'] : null,
                ]],
                'contacts' => array_values(array_filter([
                    ['type' => 'MOBILE', 'value' => $data['mobile'], 'is_primary' => true],
                    ($data['email'] ?? '') !== ''
                        ? ['type' => 'EMAIL', 'value' => $data['email'], 'is_primary' => true]
                        : null,
                ])),
            ];

            if ($existingPerson !== null) {
                $person = $this->persons->update($existingPerson, $personData);
            } else {
                $person = $this->persons->create($personData + [
                    'nic' => $nic !== '' ? $nic : null,
                    'passport_no' => $passport !== '' ? $passport : null,
                ]);
            }

            $person->emergencyContacts()->create([
                'name' => $data['emergency_contact_name'],
                'relationship' => $data['emergency_contact_relationship'],
                'phone' => $data['emergency_contact_phone'],
            ]);

            $employmentData = [
                'type' => $type,
                'hire_date' => $hireDate,
                'epf_liable' => ! $isIntern,
                'epf_no' => $epfNo,
                'employee_no' => $employeeNo,
                'hrmis_active' => $hrmisActive,
                'attendance_active' => $attendanceActive,
                'payroll_active' => $payrollActive,
                'hostel_available' => $hostelAvailable,
                'food_breakfast' => $foodBreakfast,
                'food_lunch' => $foodLunch,
                'food_dinner' => $foodDinner,
                'confirmation_due_date' => $confirmationDue,
                'employee_category_id' => $categoryId,
                'staff_work_category_id' => null,
            ];

            $assignment = [
                'org_unit_id' => $orgUnit->id,
                'designation_id' => $designation->id,
                'location_id' => $location->id,
                'manager_employment_id' => $managerId,
            ];

            $employment = $mode === 'REJOIN'
                ? $this->employments->rejoin($person, $company, $employmentData, $assignment)
                : $this->employments->hire($person, $company, $employmentData, $assignment);

            $this->banks->set($employment, $bankData);

            $this->compensation->set($employment, $hireDate, [
                'basic_salary' => $basicSalary,
                'ot_rate_mode' => $otRateMode,
                'ot_rate' => $otRate,
                'attendance_tier_code' => $attendanceType,
                'change_reason' => 'Hire',
            ]);

            if ($earningType !== '') {
                $this->compensation->addComponent($employment, strtoupper($earningType), str_replace(',', '', $earningAmount), $hireDate);
            }

            if ($deductionType !== '') {
                $this->compensation->addComponent($employment, strtoupper($deductionType), str_replace(',', '', $deductionAmount), $hireDate);
            }

            $this->timeProfiles->set($employment, [
                'effective_from' => $hireDate,
                'shift_id' => $shift->id,
                'attendance_mode' => AttendanceMode::PUNCH->value,
                'ot_mode' => $otMode,
                'late_deduction' => $lateDeduction,
            ]);

            if ($canEnrol) {
                $this->enrolments->enrol($employment, $deviceGroup, (int) $fingerprintId, $hireDate);
            }

            if ($resignDate !== null) {
                $employment = $this->employments->exit($employment, $resignDate, ExitReason::RESIGNATION);
            }

            return $employment->fresh();
        });
    }

    /**
     * @param  array<string, string>  $data
     * @return array<string, string>
     */
    private function normalizeAliases(array $data): array
    {
        if (($data['location'] ?? '') === '' && ($data['shop_office'] ?? '') !== '') {
            $data['location'] = $data['shop_office'];
        }

        if (($data['org_unit'] ?? '') === '' && ($data['department'] ?? '') !== '') {
            $data['org_unit'] = $data['department'];
        }

        if (($data['landline_no'] ?? '') === '' && ($data['landing_no'] ?? '') !== '') {
            $data['landline_no'] = $data['landing_no'];
        }

        if (($data['arrive_work_from'] ?? '') === '' && ($data['arrive_from'] ?? '') !== '') {
            $data['arrive_work_from'] = $data['arrive_from'];
        }

        if (($data['fingerprint_id'] ?? '') === '' && ($data['attendance_id'] ?? '') !== '') {
            $data['fingerprint_id'] = $data['attendance_id'];
        }

        return $data;
    }

    /**
     * @param  array<string, string>  $data
     * @return array<string, string>
     */
    private function blankPlaceholders(array $data): array
    {
        $placeholders = ['-', '—', '–', '.', 'n/a', 'na', 'nil', 'null'];

        foreach ($data as $key => $value) {
            $trimmed = trim((string) $value);
            if (in_array(mb_strtolower($trimmed), $placeholders, true) || in_array($trimmed, ['-', '—', '–', '.'], true)) {
                $data[$key] = '';
            }
        }

        return $data;
    }

    /**
     * Excel rows often omit wizard-required fields. Fill safe defaults instead of failing.
     *
     * @param  array<string, string>  $data
     * @return array<string, string>
     */
    private function applyDefaults(array $data): array
    {
        if (($data['full_name'] ?? '') !== '') {
            $parts = preg_split('/\s+/', trim($data['full_name'])) ?: [];
            if (($data['first_name'] ?? '') === '' && $parts !== []) {
                $data['first_name'] = $parts[0];
            }
            if (($data['last_name'] ?? '') === '' && count($parts) > 1) {
                $data['last_name'] = $parts[count($parts) - 1];
            } elseif (($data['last_name'] ?? '') === '' && ($data['first_name'] ?? '') !== '') {
                $data['last_name'] = $data['first_name'];
            }
        }

        if (($data['first_name'] ?? '') === '') {
            $data['first_name'] = 'Unknown';
        }
        if (($data['last_name'] ?? '') === '') {
            $data['last_name'] = $data['first_name'];
        }
        if (($data['calling_name'] ?? '') === '') {
            $data['calling_name'] = $data['first_name'];
        }
        if (($data['name_with_initials'] ?? '') === '') {
            $initial = mb_strtoupper(mb_substr($data['first_name'], 0, 1));
            $data['name_with_initials'] = $initial.'. '.$data['last_name'];
        }
        if (($data['full_name'] ?? '') === '') {
            $data['full_name'] = trim($data['first_name'].' '.(($data['middle_name'] ?? '') !== '' ? $data['middle_name'].' ' : '').$data['last_name']);
        }

        if (($data['gender'] ?? '') === '') {
            $data['gender'] = Gender::MALE->value;
        }
        if (($data['type'] ?? '') === '') {
            $data['type'] = EmploymentType::PERMANENT->value;
        }
        if (($data['hire_date'] ?? '') === '') {
            $data['hire_date'] = date('Y-m-d');
        }
        if (($data['date_of_birth'] ?? '') === '') {
            $dob = null;
            if (($data['nic'] ?? '') !== '') {
                try {
                    $dob = Nic::dateOfBirth(Nic::normalise($data['nic']))->toDateString();
                } catch (\Throwable) {
                    $dob = null;
                }
            }
            $data['date_of_birth'] = $dob ?? '1990-01-01';
        }
        if (($data['mobile'] ?? '') === '') {
            $data['mobile'] = '0000000000';
        }
        if (($data['address_line_1'] ?? '') === '') {
            $data['address_line_1'] = '-';
        }
        if (($data['basic_salary'] ?? '') === '') {
            $data['basic_salary'] = '1.00';
        }
        if (($data['paid_in_cash'] ?? '') === '') {
            $data['paid_in_cash'] = 'Yes';
        }
        if (($data['emergency_contact_name'] ?? '') === '') {
            $data['emergency_contact_name'] = 'N/A';
        }
        if (($data['emergency_contact_relationship'] ?? '') === '') {
            $data['emergency_contact_relationship'] = 'N/A';
        }
        if (($data['emergency_contact_phone'] ?? '') === '') {
            $data['emergency_contact_phone'] = '0000000000';
        }

        return $data;
    }

    private function resolveDesignation(string $value): ?Designation
    {
        $value = trim($value);
        if ($value === '') {
            return Designation::query()->where('is_active', true)->orderBy('name')->first();
        }

        return Designation::query()->where('is_active', true)->where('name', $value)->first()
            ?? Designation::query()->where('is_active', true)->whereRaw('LOWER(name) = ?', [mb_strtolower($value)])->first()
            ?? Designation::query()->where('is_active', true)->orderBy('name')->first();
    }

    /**
     * @return array{0: string, 1: string, 2: string, 3: string} nic, passport, dob, gender
     */
    private function reconcileIdentity(
        string $nic,
        string $passport,
        string $dateOfBirth,
        string $gender,
        ?string $employeeNo,
    ): array {
        $nic = trim($nic);
        $passport = trim($passport);

        if ($nic !== '') {
            try {
                $normalised = Nic::normalise($nic);
                $nic = $normalised;
                $dateOfBirth = Nic::dateOfBirth($normalised)->toDateString();
                $gender = Nic::gender($normalised)->value;
            } catch (InvalidNic) {
                $nic = '';
            }
        }

        if ($nic === '' && $passport === '') {
            $suffix = $employeeNo !== null && $employeeNo !== ''
                ? preg_replace('/\s+/', '', $employeeNo)
                : uniqid('IMP', true);
            $passport = 'EXCEL-'.$suffix;
        }

        return [$nic, $passport, $dateOfBirth, $gender];
    }

    private function normalizeMaritalStatus(string $value): ?string
    {
        $trimmed = trim($value);
        if ($trimmed === '') {
            return null;
        }

        $key = strtolower(preg_replace('/\s+/', '', $trimmed) ?? '');
        $aliases = [
            'single' => MaritalStatus::SINGLE->value,
            'unmarried' => MaritalStatus::SINGLE->value,
            'married' => MaritalStatus::MARRIED->value,
            'divorce' => MaritalStatus::DIVORCED->value,
            'divorced' => MaritalStatus::DIVORCED->value,
            'widow' => MaritalStatus::WIDOWED->value,
            'widowed' => MaritalStatus::WIDOWED->value,
            'widower' => MaritalStatus::WIDOWED->value,
        ];

        if (isset($aliases[$key])) {
            return $aliases[$key];
        }

        return MaritalStatus::tryFrom(strtoupper($trimmed))?->value;
    }

    private function normalizeArriveWorkFrom(string $value): ?string
    {
        $trimmed = trim($value);
        if ($trimmed === '') {
            return null;
        }

        $key = strtolower(preg_replace('/\s+/', '', $trimmed) ?? '');
        $aliases = [
            'home' => ArriveWorkFrom::HOME->value,
            'boarding' => ArriveWorkFrom::BOARDING->value,
        ];

        if (isset($aliases[$key])) {
            return $aliases[$key];
        }

        return ArriveWorkFrom::tryFrom(strtoupper($trimmed))?->value;
    }

    private function resolveShift(string $value): ?Shift
    {
        $value = trim($value);
        if ($value === '') {
            return Shift::query()->where('is_active', true)->orderBy('name')->first();
        }

        $byNameOrCode = Shift::query()
            ->where('is_active', true)
            ->where(function ($q) use ($value) {
                $q->where('name', $value)->orWhere('code', $value);
            })
            ->first();
        if ($byNameOrCode !== null) {
            return $byNameOrCode;
        }

        $range = $this->parseShiftTimeRange($value);
        if ($range === null) {
            return null;
        }

        [$start, $end] = $range;

        return Shift::query()
            ->where('is_active', true)
            ->get()
            ->first(function (Shift $shift) use ($start, $end) {
                return $this->normalizeClock((string) $shift->start_time) === $start
                    && $this->normalizeClock((string) $shift->end_time) === $end;
            });
    }

    /** @return array{0: string, 1: string}|null */
    private function parseShiftTimeRange(string $value): ?array
    {
        // Accept "07.30-16.30", "07:30–16:30", "Day 07:30-16:30", etc.
        if (preg_match('/(\d{1,2})[:.](\d{2})\s*[-–—]\s*(\d{1,2})[:.](\d{2})/', $value, $m) !== 1) {
            return null;
        }

        $start = sprintf('%02d:%02d', (int) $m[1], (int) $m[2]);
        $end = sprintf('%02d:%02d', (int) $m[3], (int) $m[4]);

        return [$start, $end];
    }

    private function normalizeClock(string $time): string
    {
        $time = trim($time);
        if (preg_match('/^(\d{1,2}):(\d{2})(?::\d{2})?/', $time, $m) === 1) {
            return sprintf('%02d:%02d', (int) $m[1], (int) $m[2]);
        }

        return $time;
    }

    private function normalizeOtMode(string $value): ?string
    {
        $trimmed = trim($value);
        if ($trimmed === '') {
            return OtMode::NONE->value;
        }

        $key = strtolower(preg_replace('/\s+/', '', $trimmed) ?? '');

        $aliases = [
            'default' => OtMode::NONE->value,
            'none' => OtMode::NONE->value,
            'morning' => OtMode::MORNING->value,
            'morningonly' => OtMode::MORNING->value,
            'evening' => OtMode::EVENING->value,
            'eveningonly' => OtMode::EVENING->value,
            'both' => OtMode::BOTH->value,
            '-1' => OtMode::NONE->value,
            '0' => OtMode::NONE->value,
            '1' => OtMode::MORNING->value,
            '2' => OtMode::EVENING->value,
            '3' => OtMode::BOTH->value,
        ];

        if (isset($aliases[$key])) {
            return $aliases[$key];
        }

        return OtMode::tryFrom(strtoupper($trimmed))?->value;
    }

    private function normalizeEmploymentType(string $value): ?string
    {
        $trimmed = trim($value);
        if ($trimmed === '') {
            return EmploymentType::PERMANENT->value;
        }

        $key = strtolower(preg_replace('/\s+/', '', $trimmed) ?? '');

        $aliases = [
            'permenent' => EmploymentType::PERMANENT->value,
            'permanent' => EmploymentType::PERMANENT->value,
            'probation' => EmploymentType::PROBATION->value,
            'intern' => EmploymentType::INTERN->value,
            'contract' => EmploymentType::CONTRACT->value,
        ];

        if (isset($aliases[$key])) {
            return $aliases[$key];
        }

        return EmploymentType::tryFrom(strtoupper($trimmed))?->value;
    }

    private function normalizeDate(string $value, string $label): string
    {
        if (is_numeric($value)) {
            try {
                return Date::excelToDateTimeObject((float) $value)->format('Y-m-d');
            } catch (\Throwable) {
                throw new \InvalidArgumentException("{$label} is not a valid date.");
            }
        }

        $timestamp = strtotime($value);
        if ($timestamp === false) {
            throw new \InvalidArgumentException("{$label} is not a valid date.");
        }

        return date('Y-m-d', $timestamp);
    }

    private function parseYesNo(string $value, string $label): bool
    {
        $key = strtolower(preg_replace('/\s+/', '', trim($value)) ?? '');

        $map = [
            'yes' => true,
            'no' => false,
            'y' => true,
            'n' => false,
            '1' => true,
            '0' => false,
            'true' => true,
            'false' => false,
            'active' => true,
            'inactive' => false,
            'default' => false,
            '-1' => false,
            'direct' => true,
            'indirect' => true,
        ];

        if (! array_key_exists($key, $map)) {
            throw new \InvalidArgumentException("{$label} must be Yes or No.");
        }

        return $map[$key];
    }
}
