<?php

namespace Modules\CoreHr\Livewire;

use App\Exceptions\Domain\DomainException;
use App\Support\Company\CompanyContext;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Component;
use Modules\Attendance\Enums\AttendanceMode;
use Modules\Attendance\Enums\OtMode;
use Modules\Attendance\Exceptions\DeviceUserIdInUse;
use Modules\Attendance\Models\DeviceGroup;
use Modules\Attendance\Models\Shift;
use Modules\Attendance\Services\EnrolmentService;
use Modules\Attendance\Services\TimeProfileService;
use Modules\CoreHr\Enums\ArriveWorkFrom;
use Modules\CoreHr\Enums\ContactType;
use Modules\CoreHr\Enums\EmploymentStatus;
use Modules\CoreHr\Enums\EmploymentType;
use Modules\CoreHr\Enums\Gender;
use Modules\CoreHr\Enums\MaritalStatus;
use Modules\CoreHr\Exceptions\InvalidNic;
use Modules\CoreHr\Livewire\Concerns\LoadsAssignmentOptions;
use Modules\CoreHr\Livewire\Concerns\LoadsBankOptions;
use Modules\CoreHr\Models\Employment;
use Modules\CoreHr\Models\GnDivision;
use Modules\CoreHr\Models\Person;
use Modules\CoreHr\Models\Region;
use Modules\CoreHr\Models\Relationship;
use Modules\CoreHr\Services\BankAccountService;
use Modules\CoreHr\Services\EmployeeNumberGenerator;
use Modules\CoreHr\Services\EmploymentService;
use Modules\CoreHr\Services\EpfNumberGenerator;
use Modules\CoreHr\Services\PersonService;
use Modules\CoreHr\Support\EmployeesNav;
use Modules\CoreHr\Support\Nic;
use Modules\CoreHr\Support\OrgUnitOptions;
use Modules\CoreHr\Support\SriLankaGeo;
use Modules\Organisation\Models\Company;
use Modules\Organisation\Models\Designation;
use Modules\Organisation\Models\EmployeeCategory;
use Modules\Organisation\Models\Grade;
use Modules\Organisation\Models\Location;
use Modules\Organisation\Models\OrgUnit;
use Modules\Organisation\Models\StaffWorkCategory;
use Modules\Payroll\Enums\CalcMethod;
use Modules\Payroll\Enums\ComponentKind;
use Modules\Payroll\Enums\OtRateMode;
use Modules\Payroll\Models\AttendanceAllowanceTier;
use Modules\Payroll\Models\PayComponent;
use Modules\Payroll\Services\CompensationService;

class HireWizard extends Component
{
    use LoadsAssignmentOptions;
    use LoadsBankOptions;

    private const PHONE_REGEX = '/^0[0-9]{9}$/';

    public int $step = 1;

    /** HIRE for a new employment of a new or existing person, REJOIN when the person left this company before. */
    public string $mode = 'HIRE';

    // Step 1: identify
    public string|int|null $companyId = null;

    public string $nic = '';

    public string $passportNo = '';

    public ?int $existingPersonId = null;

    public ?int $prefilledPersonId = null;

    public bool $identityLocked = false;

    public string $alreadyEmployedMessage = '';

    public ?int $alreadyEmployedId = null;

    /** @var list<array{company: string, employee_no: string, hire_date: string, exit_date: string, exit_reason: string}> */
    public array $pastEmployments = [];

    /** @var list<string> */
    public const NATIONALITIES = ['Sri Lankan', 'Nepalese', 'Indian'];

    // Step 2: personal
    public string $fullName = '';

    public string $nameWithInitials = '';

    public string $callingName = '';

    public string $dateOfBirth = '';

    public string $gender = '';

    public string $maritalStatus = '';

    public string $nationality = 'Sri Lankan';

    public string $drivingLicenceNo = '';

    public string $landlineNo = '';

    public string $officeNo = '';

    public string $region = '';

    public string $arriveWorkFrom = '';

    public string $province = '';

    public string $electorateDistrict = '';

    public string $gnDivision = '';

    public string $distanceKm = '';

    public string $mobile = '';

    public string $email = '';

    public string $addressLine1 = '';

    public string $city = '';

    /** @var list<array{name: string, relationship: string, phone: string}> */
    public array $emergencyContacts = [
        ['name' => '', 'relationship' => '', 'phone' => ''],
    ];

    // Step 3: employment
    public string $type = '';

    public string $hireDate = '';

    public string $confirmationDueDate = '';

    public bool $epfLiable = true;

    public bool $hrmisActive = true;

    public bool $attendanceActive = true;

    public bool $payrollActive = true;

    public string|int|null $employeeCategoryId = null;

    public string|int|null $staffWorkCategoryId = null;

    public string|int|null $departmentId = null;

    public string|int|null $orgUnitId = null;

    public string|int|null $designationId = null;

    public string|int|null $gradeId = null;

    public string|int|null $locationId = null;

    public string|int|null $shiftId = null;

    public string $attendanceMode = 'PUNCH';

    public string $otMode = 'NONE';

    public bool $lateDeduction = false;

    // Step 4: payroll, bank and fingerprint
    public string $basicSalary = '';

    public string $otRateMode = 'CALCULATED';

    public string $otRate = '';

    public string $attendanceTierCode = '';

    public bool $isCash = false;

    public string|int|null $bankId = null;

    public string|int|null $bankBranchId = null;

    public string $accountNo = '';

    public string $accountName = '';

    public string $deviceGroup = '';

    public string|int|null $deviceUserId = null;

    /** @var list<array{code: string, amount: string}> */
    public array $earningComponents = [['code' => '', 'amount' => '']];

    /** @var list<array{code: string, amount: string}> */
    public array $deductionComponents = [['code' => '', 'amount' => '']];

    public bool $hostelAvailable = false;

    public bool $foodBreakfast = false;

    public bool $foodLunch = false;

    public bool $foodDinner = false;

    public string $hostelPaidAmount = '';

    // Step 5: review
    public string $domainError = '';

    public function mount(): void
    {
        CompanyContext::ensureSoleSelected();
        $this->companyId = CompanyContext::id();
        $this->hireDate = CarbonImmutable::now(config('app.timezone'))->toDateString();
    }

    public function updatedType(): void
    {
        if ($this->type === EmploymentType::INTERN->value) {
            $this->epfLiable = false;
            $this->basicSalary = '0.00';
        } else {
            $this->epfLiable = ! $this->isInternDesignation();
            if ($this->basicSalary === '0.00') {
                $this->basicSalary = '';
            }
        }

        $this->setConfirmationDueDate();
    }

    public function updatedDesignationId(): void
    {
        $this->epfLiable = ! $this->isInternForEpf();
    }

    public function updatedAttendanceActive(): void
    {
        if (! $this->attendanceActive) {
            $this->deviceGroup = '';
            $this->deviceUserId = null;
        }
    }

    /** True when Type is INTERN or Designation is Intern (code or name). */
    public function isInternForEpf(): bool
    {
        return $this->type === EmploymentType::INTERN->value || $this->isInternDesignation();
    }

    private function isInternDesignation(): bool
    {
        if ($this->designationId === null || $this->designationId === '') {
            return false;
        }

        $designation = Designation::query()
            ->whereKey((int) $this->designationId)
            ->first(['code', 'name', 'name_key']);

        if ($designation === null) {
            return false;
        }

        if (strtoupper((string) $designation->code) === 'INTERN') {
            return true;
        }

        return strtolower(trim((string) $designation->name)) === 'intern'
            || strtolower(trim((string) $designation->name_key)) === 'intern';
    }

    public function updatedDepartmentId(): void
    {
        $this->orgUnitId = null;
        $companyId = $this->companyId === null || $this->companyId === '' ? null : (int) $this->companyId;
        $departmentId = $this->departmentId === null || $this->departmentId === '' ? null : (int) $this->departmentId;

        if ($departmentId === null || $companyId === null) {
            return;
        }

        if (OrgUnitOptions::underDepartment($departmentId, $companyId) === []) {
            $this->orgUnitId = $departmentId;
        }
    }

    public function updatedBasicSalary(): void
    {
        $this->basicSalary = $this->formatMoneyLive($this->basicSalary);
    }

    public function updatedOtRate(): void
    {
        $this->otRate = $this->formatMoneyLive($this->otRate);
    }

    public function updatedEarningComponents($value, ?string $key = null): void
    {
        if ($key === null || ! str_ends_with($key, '.amount')) {
            return;
        }

        $rows = $this->earningComponents;
        data_set($rows, $key, $this->formatMoneyLive((string) $value));
        $this->earningComponents = $rows;
    }

    public function updatedDeductionComponents($value, ?string $key = null): void
    {
        if ($key === null || ! str_ends_with($key, '.amount')) {
            return;
        }

        $rows = $this->deductionComponents;
        data_set($rows, $key, $this->formatMoneyLive((string) $value));
        $this->deductionComponents = $rows;
    }

    public function updatedHostelAvailable(): void
    {
        if (! $this->hostelAvailable) {
            $this->hostelPaidAmount = '';
        }
    }

    public function updatedHostelPaidAmount(): void
    {
        $this->hostelPaidAmount = $this->formatMoneyLive($this->hostelPaidAmount);
    }

    public function updatedHireDate(): void
    {
        $this->setConfirmationDueDate();
    }

    public function updatedFullName(): void
    {
        $this->nameWithInitials = $this->initialsFromFullName($this->fullName);
    }

    public function updatedProvince(): void
    {
        $allowed = SriLankaGeo::districtsForProvince($this->province);
        if ($this->electorateDistrict !== '' && ! in_array($this->electorateDistrict, $allowed, true)) {
            $this->electorateDistrict = '';
            $this->gnDivision = '';
        }
    }

    public function addEmergencyContact(): void
    {
        $this->emergencyContacts[] = ['name' => '', 'relationship' => '', 'phone' => ''];
    }

    public function removeEmergencyContact(int $index): void
    {
        if (count($this->emergencyContacts) <= 1) {
            $this->emergencyContacts = [['name' => '', 'relationship' => '', 'phone' => '']];

            return;
        }

        unset($this->emergencyContacts[$index]);
        $this->emergencyContacts = array_values($this->emergencyContacts);
    }

    public function addEarningComponent(): void
    {
        $this->earningComponents[] = ['code' => '', 'amount' => ''];
    }

    public function removeEarningComponent(int $index): void
    {
        $this->earningComponents = $this->removeComponentRow($this->earningComponents, $index);
    }

    public function addDeductionComponent(): void
    {
        $this->deductionComponents[] = ['code' => '', 'amount' => ''];
    }

    public function removeDeductionComponent(int $index): void
    {
        $this->deductionComponents = $this->removeComponentRow($this->deductionComponents, $index);
    }

    /**
     * @param  list<array{code: string, amount: string}>  $rows
     * @return list<array{code: string, amount: string}>
     */
    private function removeComponentRow(array $rows, int $index): array
    {
        if (count($rows) <= 1) {
            return [['code' => '', 'amount' => '']];
        }

        unset($rows[$index]);

        return array_values($rows);
    }

    public function updatedElectorateDistrict(): void
    {
        $suggestions = GnDivision::namesForDistrictName($this->electorateDistrict);
        if ($this->gnDivision !== '' && $suggestions !== [] && ! in_array($this->gnDivision, $suggestions, true)) {
            $this->gnDivision = '';
        }
    }

    public function next(): void
    {
        $this->authorize('employee.create');
        $this->domainError = '';

        match ($this->step) {
            1 => $this->completeIdentify(),
            2 => $this->completePersonal(),
            3 => $this->completeEmployment(),
            4 => $this->completeBank(),
            default => null,
        };
    }

    public function back(): void
    {
        $this->domainError = '';
        $this->resetErrorBag();
        $this->step = max(1, $this->step - 1);
    }

    public function hire(
        PersonService $persons,
        EmploymentService $employments,
        BankAccountService $banks,
        EnrolmentService $enrolments,
        TimeProfileService $timeProfiles,
        CompensationService $compensation,
    ): void {
        $this->authorize('employee.create');
        $this->domainError = '';

        foreach ([2, 3, 4] as $step) {
            $this->validateStep($step);
        }

        try {
            $employment = DB::transaction(function () use ($persons, $employments, $banks, $enrolments, $timeProfiles, $compensation) {
                $personData = $this->personData();

                if ($this->existingPersonId !== null) {
                    $person = $persons->update(Person::query()->findOrFail($this->existingPersonId), $personData);
                } else {
                    $person = $persons->create($personData + [
                        'nic' => trim($this->nic) !== '' ? trim($this->nic) : null,
                        'passport_no' => trim($this->passportNo) !== '' ? trim($this->passportNo) : null,
                    ]);
                }

                foreach ($this->filledEmergencyContacts() as $contact) {
                    $person->emergencyContacts()->create([
                        'name' => $contact['name'],
                        'relationship' => $contact['relationship'],
                        'phone' => $contact['phone'],
                    ]);
                }

                $company = Company::query()->findOrFail((int) $this->companyId);
                $employmentData = $this->employmentData();
                $assignment = $this->assignmentData();

                $employment = $this->mode === 'REJOIN'
                    ? $employments->rejoin($person, $company, $employmentData, $assignment)
                    : $employments->hire($person, $company, $employmentData, $assignment);

                $banks->set($employment, $this->bankData());

                $compensation->set($employment, $this->hireDate, $this->compensationData());

                foreach ($this->filledPayComponents() as $row) {
                    $compensation->addComponent($employment, $row['code'], $row['amount'], $this->hireDate);
                }

                $timeProfiles->set($employment, [
                    'effective_from' => $this->hireDate,
                    'shift_id' => (int) $this->shiftId,
                    'attendance_mode' => AttendanceMode::PUNCH->value,
                    'ot_mode' => $this->otMode,
                    'late_deduction' => $this->lateDeduction,
                ]);

                if ($this->attendanceActive && trim($this->deviceGroup) !== '') {
                    $enrolments->enrol($employment, trim($this->deviceGroup), (int) $this->deviceUserId, $this->hireDate);
                }

                return $employment;
            });
        } catch (DomainException|\InvalidArgumentException $e) {
            $this->failWith($e->getMessage());

            return;
        } catch (UniqueConstraintViolationException) {
            $this->failWith('This person already has an active employment.');

            return;
        }

        session()->flash('success', "Hired as {$employment->employee_no}.");
        $this->redirect(route('employees.show', $employment));
    }

    private function completeIdentify(): void
    {
        $this->companyId = CompanyContext::id();
        $permitted = CompanyContext::permittedIds();

        $this->validate([
            'companyId' => ['required', 'integer', Rule::in($permitted)],
            'nic' => ['nullable', 'string', 'max:20'],
            'passportNo' => ['nullable', 'string', 'max:20'],
        ], [
            'companyId.required' => 'Select a company in the header first.',
            'companyId.in' => 'Select a company in the header first.',
        ]);

        $nic = trim($this->nic);
        $passport = trim($this->passportNo);

        if ($nic === '' && $passport === '') {
            $this->addError('nic', 'Enter a NIC or a passport number.');

            return;
        }

        $normalised = null;
        if ($nic !== '') {
            try {
                $normalised = Nic::normalise($nic);
            } catch (InvalidNic $e) {
                $this->addError('nic', $e->getMessage());

                return;
            }
        }

        $this->alreadyEmployedMessage = '';
        $this->alreadyEmployedId = null;
        $this->pastEmployments = [];

        $person = $normalised !== null
            ? app(PersonService::class)->findByNic($nic)
            : Person::query()->where('passport_no', $passport)->first();

        if ($person === null) {
            $this->startNewPerson($normalised);
            $this->step = 2;

            return;
        }

        $active = Employment::query()
            ->with('company')
            ->where('person_id', $person->id)
            ->where('status', '!=', EmploymentStatus::EXITED)
            ->first();

        if ($active !== null) {
            $this->alreadyEmployedId = $active->id;
            $this->alreadyEmployedMessage = "This person is already employed ({$active->employee_no}, {$active->company->name}).";

            return;
        }

        $past = Employment::query()
            ->with('company')
            ->where('person_id', $person->id)
            ->orderByDesc('exit_date')
            ->get();

        $this->pastEmployments = $past->map(fn (Employment $e) => [
            'company' => $e->company->name,
            'employee_no' => $e->employee_no,
            'hire_date' => $e->hire_date->toDateString(),
            'exit_date' => $e->exit_date?->toDateString() ?? '',
            'exit_reason' => $e->exit_reason ? ucfirst(strtolower(str_replace('_', ' ', $e->exit_reason->value))) : '',
        ])->all();

        $this->mode = $past->contains(fn (Employment $e) => (int) $e->company_id === (int) $this->companyId) ? 'REJOIN' : 'HIRE';
        $this->existingPersonId = $person->id;

        if ($this->prefilledPersonId !== $person->id) {
            $this->fillFromPerson($person);
            $this->prefilledPersonId = $person->id;
        }

        $this->step = 2;
    }

    private function startNewPerson(?string $normalisedNic): void
    {
        if ($this->prefilledPersonId !== null) {
            // The earlier lookup matched somebody else; their details must not stay in the form.
            $this->reset([
                'fullName', 'nameWithInitials', 'callingName', 'maritalStatus',
                'drivingLicenceNo', 'landlineNo', 'officeNo', 'region', 'arriveWorkFrom',
                'province', 'electorateDistrict', 'gnDivision', 'distanceKm',
                'mobile', 'email', 'addressLine1', 'city',
            ]);
            $this->nationality = 'Sri Lankan';
        }

        $this->existingPersonId = null;
        $this->prefilledPersonId = null;
        $this->mode = 'HIRE';

        if ($normalisedNic !== null) {
            $this->dateOfBirth = Nic::dateOfBirth($normalisedNic)->toDateString();
            $this->gender = Nic::gender($normalisedNic)->value;
            $this->identityLocked = true;
        } else {
            $this->identityLocked = false;
        }
    }

    private function fillFromPerson(Person $person): void
    {
        $person->load(['contacts', 'addresses']);

        $this->fullName = trim((string) ($person->full_name ?: implode(' ', array_filter([
            $person->first_name,
            $person->middle_name,
            $person->last_name,
        ]))));
        $this->nameWithInitials = $person->name_with_initials;
        $this->callingName = (string) $person->calling_name;
        $this->dateOfBirth = $person->date_of_birth->toDateString();
        $this->gender = $person->gender->value;
        $this->maritalStatus = $person->marital_status?->value ?? '';
        $this->nationality = (string) ($person->nationality ?: 'Sri Lankan');
        $this->drivingLicenceNo = (string) $person->driving_licence_no;
        $this->landlineNo = (string) $person->landline_no;
        $this->officeNo = (string) $person->office_no;
        $this->region = (string) $person->region;
        $this->arriveWorkFrom = $person->arrive_work_from?->value ?? '';
        $this->province = (string) $person->province;
        $this->electorateDistrict = (string) $person->electorate_district;
        $this->gnDivision = (string) $person->gn_division;
        $this->distanceKm = $person->distance_km !== null ? (string) $person->distance_km : '';
        $this->identityLocked = $person->nic !== null;

        $mobile = $person->contacts->where('type', ContactType::MOBILE)->sortByDesc('is_primary')->first();
        $email = $person->contacts->where('type', ContactType::EMAIL)->sortByDesc('is_primary')->first();
        $this->mobile = (string) $mobile?->value;
        $this->email = (string) $email?->value;

        $address = $person->addresses->first(fn ($a) => $a->type->value === 'PERMANENT');
        $this->addressLine1 = (string) $address?->line1;
        $this->city = (string) $address?->city;
    }

    private function completePersonal(): void
    {
        $this->validateStep(2);
        $this->attendanceMode = AttendanceMode::PUNCH->value;
        $this->ensureDefaultGradeAndLocation();
        $this->step = 3;
    }

    private function completeEmployment(): void
    {
        $this->attendanceMode = AttendanceMode::PUNCH->value;
        $this->ensureDefaultGradeAndLocation();
        $this->validateStep(3);
        $this->step = 4;

        if (trim($this->accountName) === '') {
            $this->accountName = $this->nameWithInitials;
        }
    }

    private function completeBank(): void
    {
        $this->validateStep(4);
        $this->step = 5;
    }

    /** Validates one step. A failure sends the wizard back to that step. */
    private function validateStep(int $step): void
    {
        try {
            if ($step === 4) {
                $this->normalizeMoneyFields();
            }

            $this->validate($this->rulesFor($step), $this->messagesFor($step));
            if ($step === 4) {
                $this->assertUniquePayComponentCodes();
                $this->assertFingerprintAvailable();
                $this->reformatMoneyFields();
            }
        } catch (ValidationException $e) {
            if ($step === 4) {
                $this->reformatMoneyFields();
            }
            $this->step = $step;

            throw $e;
        }
    }

    /** @return array<string, mixed> */
    private function rulesFor(int $step): array
    {
        return match ($step) {
            2 => array_merge([
                'fullName' => ['required', 'string', 'max:150'],
                'nameWithInitials' => ['required', 'string', 'max:150'],
                'callingName' => ['required', 'string', 'max:100'],
                'dateOfBirth' => ['required', 'date_format:Y-m-d'],
                'gender' => ['required', Rule::enum(Gender::class)],
                'maritalStatus' => ['required', Rule::enum(MaritalStatus::class)],
                'nationality' => ['required', 'string', 'max:50', Rule::in(self::NATIONALITIES)],
                'drivingLicenceNo' => ['nullable', 'string', 'max:50'],
                'landlineNo' => ['nullable', 'string', 'max:20', 'regex:'.self::PHONE_REGEX],
                'officeNo' => ['nullable', 'string', 'max:20', 'regex:'.self::PHONE_REGEX],
                'region' => ['required', 'string', 'max:50', Rule::in(Region::activeNames())],
                'arriveWorkFrom' => ['required', Rule::enum(ArriveWorkFrom::class)],
                'province' => ['required', 'string', 'max:50', Rule::in(SriLankaGeo::provinces())],
                'electorateDistrict' => ['required', 'string', 'max:50', Rule::in(SriLankaGeo::districts())],
                'gnDivision' => $this->gnDivisionRules(),
                'distanceKm' => ['nullable', 'numeric', 'min:0'],
                'mobile' => ['required', 'string', 'regex:'.self::PHONE_REGEX],
                'email' => ['nullable', 'email', 'max:150'],
                'addressLine1' => ['required', 'string', 'max:150'],
                'city' => ['required', 'string', 'max:80'],
                'emergencyContacts' => ['required', 'array', 'min:1'],
            ], $this->emergencyContactRules()),
            3 => [
                'type' => ['required', Rule::enum(EmploymentType::class)],
                'hireDate' => ['required', 'date_format:Y-m-d'],
                'confirmationDueDate' => ['nullable', 'date_format:Y-m-d'],
                'hrmisActive' => ['boolean'],
                'attendanceActive' => ['boolean'],
                'payrollActive' => ['boolean'],
                'employeeCategoryId' => [
                    'required',
                    'integer',
                    Rule::exists('employee_categories', 'id')->where('is_active', true),
                ],
                'staffWorkCategoryId' => [
                    'nullable',
                    'integer',
                    Rule::exists('staff_work_categories', 'id')->where('is_active', true),
                ],
                'departmentId' => [
                    'required',
                    'integer',
                    Rule::exists('org_units', 'id')
                        ->where('company_id', (int) $this->companyId)
                        ->where('type', 'DEPARTMENT')
                        ->where('is_active', true),
                ],
                'orgUnitId' => [
                    'required',
                    'integer',
                    Rule::exists('org_units', 'id')->where('company_id', (int) $this->companyId)->where('is_active', true),
                    function (string $attribute, mixed $value, \Closure $fail): void {
                        $departmentId = $this->departmentId === null || $this->departmentId === '' ? null : (int) $this->departmentId;
                        $orgUnitId = (int) $value;
                        if ($departmentId === null) {
                            return;
                        }

                        $companyId = (int) $this->companyId;
                        $children = OrgUnitOptions::underDepartment($departmentId, $companyId);
                        if ($children === []) {
                            if ($orgUnitId !== $departmentId) {
                                $fail('Select the department when it has no sections.');
                            }

                            return;
                        }

                        $allowed = array_column($children, 'id');
                        if (! in_array($orgUnitId, $allowed, true)) {
                            $fail('Select a section or subsection under the department.');
                        }
                    },
                ],
                'designationId' => ['required', 'integer', Rule::exists('designations', 'id')],
                'gradeId' => ['required', 'integer', Rule::exists('grades', 'id')],
                'locationId' => ['required', 'integer', Rule::exists('locations', 'id')],
                'shiftId' => ['required', 'integer', Rule::exists('shifts', 'id')->where('is_active', true)],
                'otMode' => ['required', Rule::enum(OtMode::class)],
                'lateDeduction' => ['boolean'],
            ],
            4 => [
                'basicSalary' => ['required', 'string', 'max:25'],
                'otRateMode' => ['required', Rule::enum(OtRateMode::class)],
                'otRate' => [
                    $this->otRateMode === OtRateMode::FIXED->value ? 'required' : 'nullable',
                    'string',
                    'max:25',
                ],
                'attendanceTierCode' => ['nullable', 'string', 'max:10'],
                'isCash' => ['boolean'],
                'bankId' => [$this->isCash ? 'nullable' : 'required', 'integer'],
                'bankBranchId' => [$this->isCash ? 'nullable' : 'required', 'integer'],
                'accountNo' => [$this->isCash ? 'nullable' : 'required', 'string', 'max:30'],
                'accountName' => [$this->isCash ? 'nullable' : 'required', 'string', 'max:150'],
                'deviceGroup' => [
                    $this->attendanceActive ? 'required' : 'nullable',
                    'string',
                    'max:20',
                    'required_with:deviceUserId',
                    Rule::exists('device_groups', 'code')->where('is_active', true),
                ],
                'deviceUserId' => [
                    $this->attendanceActive ? 'required' : 'nullable',
                    'integer',
                    'min:1',
                    'required_with:deviceGroup',
                ],
                'hostelAvailable' => ['boolean'],
                'foodBreakfast' => ['boolean'],
                'foodLunch' => ['boolean'],
                'foodDinner' => ['boolean'],
                'hostelPaidAmount' => $this->hostelAvailable
                    ? ['required', 'numeric', 'gt:0']
                    : ['nullable', 'string', 'max:25'],
            ] + $this->payComponentRules(),
            default => [],
        };
    }

    /** @return array<string, string> */
    private function messagesFor(int $step): array
    {
        return match ($step) {
            2 => [
                'mobile.regex' => 'Enter a valid 10-digit phone number starting with 0.',
                'landlineNo.regex' => 'Enter a valid 10-digit phone number starting with 0.',
                'officeNo.regex' => 'Enter a valid 10-digit phone number starting with 0.',
                'callingName.required' => 'Enter the calling name.',
                'maritalStatus.required' => 'Select the marital status.',
                'nationality.required' => 'Select the nationality.',
                'region.required' => 'Select the region.',
                'arriveWorkFrom.required' => 'Select where the employee arrives to work from.',
                'province.required' => 'Select the province.',
                'electorateDistrict.required' => 'Select the district.',
                'gnDivision.required' => 'Enter or select the GN division.',
                'emergencyContacts.required' => 'Enter at least one emergency contact.',
                'emergencyContacts.min' => 'Enter at least one emergency contact.',
                'emergencyContacts.0.name.required' => 'Enter the emergency contact name.',
                'emergencyContacts.0.relationship.required' => 'Select the emergency contact relationship.',
                'emergencyContacts.0.phone.required' => 'Enter the emergency contact phone.',
                'emergencyContacts.0.phone.regex' => 'Enter a valid 10-digit phone number starting with 0.',
                'emergencyContacts.*.name.required_with' => 'Enter the name, relationship and phone of the emergency contact together.',
                'emergencyContacts.*.relationship.required_with' => 'Enter the name, relationship and phone of the emergency contact together.',
                'emergencyContacts.*.phone.required_with' => 'Enter the name, relationship and phone of the emergency contact together.',
                'emergencyContacts.*.phone.regex' => 'Enter a valid 10-digit phone number starting with 0.',
            ],
            3 => [
                'employeeCategoryId.required' => 'Select the employee category.',
                'departmentId.required' => 'Select the department.',
                'orgUnitId.required' => 'Select the section or subsection.',
            ],
            4 => [
                'deviceGroup.required' => 'Select the device group when attendance is active.',
                'deviceGroup.exists' => 'Select an active device group.',
                'deviceUserId.required' => 'Enter the fingerprint ID when attendance is active.',
                'basicSalary.required' => 'Enter the basic salary.',
                'otRate.required' => 'Enter the fixed OT rate.',
                'deviceGroup.required_with' => 'Select the device group as well.',
                'deviceUserId.required_with' => 'Enter the device user ID as well.',
                'earningComponents.*.code.required' => 'Select the earning and amount together.',
                'earningComponents.*.code.in' => 'Select a valid earning component.',
                'earningComponents.*.amount.required' => 'Select the earning and amount together.',
                'earningComponents.*.amount.numeric' => 'Enter a valid amount.',
                'earningComponents.*.amount.gt' => 'Amount must be greater than zero.',
                'deductionComponents.*.code.required' => 'Select the deduction and amount together.',
                'deductionComponents.*.code.in' => 'Select a valid deduction component.',
                'deductionComponents.*.amount.required' => 'Select the deduction and amount together.',
                'deductionComponents.*.amount.numeric' => 'Enter a valid amount.',
                'deductionComponents.*.amount.gt' => 'Amount must be greater than zero.',
                'hostelPaidAmount.required' => 'Enter the hostel paid amount.',
                'hostelPaidAmount.numeric' => 'Enter a valid hostel amount.',
                'hostelPaidAmount.gt' => 'Hostel amount must be greater than zero.',
            ],
            default => [],
        };
    }

    /** @return list<mixed> */
    private function gnDivisionRules(): array
    {
        $rules = ['required', 'string', 'max:100'];
        $suggestions = GnDivision::namesForDistrictName($this->electorateDistrict);
        if ($suggestions !== []) {
            $rules[] = Rule::in($suggestions);
        }

        return $rules;
    }

    /** @return array<string, list<mixed>> */
    private function emergencyContactRules(): array
    {
        $relationshipNames = Relationship::activeNames();
        $rules = [];

        foreach (array_keys($this->emergencyContacts) as $index) {
            if ((int) $index === 0) {
                $rules["emergencyContacts.{$index}.name"] = ['required', 'string', 'max:150'];
                $rules["emergencyContacts.{$index}.relationship"] = ['required', 'string', 'max:50', Rule::in($relationshipNames)];
                $rules["emergencyContacts.{$index}.phone"] = ['required', 'string', 'max:20', 'regex:'.self::PHONE_REGEX];

                continue;
            }

            $rules["emergencyContacts.{$index}.name"] = [
                'nullable',
                'string',
                'max:150',
                "required_with:emergencyContacts.{$index}.relationship,emergencyContacts.{$index}.phone",
            ];
            $rules["emergencyContacts.{$index}.relationship"] = [
                'nullable',
                'string',
                'max:50',
                "required_with:emergencyContacts.{$index}.name,emergencyContacts.{$index}.phone",
                Rule::in(array_merge([''], $relationshipNames)),
            ];
            $rules["emergencyContacts.{$index}.phone"] = [
                'nullable',
                'string',
                'max:20',
                'regex:'.self::PHONE_REGEX,
                "required_with:emergencyContacts.{$index}.name,emergencyContacts.{$index}.relationship",
            ];
        }

        return $rules;
    }

    /** @return array<string, list<mixed>> */
    private function payComponentRules(): array
    {
        return $this->payComponentListRules('earningComponents', $this->earningComponents, ComponentKind::EARNING)
            + $this->payComponentListRules('deductionComponents', $this->deductionComponents, ComponentKind::DEDUCTION);
    }

    /**
     * @param  list<array{code: string, amount: string}>  $rows
     * @return array<string, list<mixed>>
     */
    private function payComponentListRules(string $field, array $rows, ComponentKind $kind): array
    {
        $attachable = $this->attachablePayComponentCodes($kind);
        $rules = [];

        foreach ($rows as $index => $row) {
            $code = trim((string) ($row['code'] ?? ''));
            $amount = trim((string) ($row['amount'] ?? ''));

            if ($code === '' && $amount === '') {
                $rules["{$field}.{$index}.code"] = ['nullable', 'string'];
                $rules["{$field}.{$index}.amount"] = ['nullable', 'string'];

                continue;
            }

            $rules["{$field}.{$index}.code"] = [
                'required',
                'string',
                Rule::in($attachable),
            ];
            $rules["{$field}.{$index}.amount"] = [
                'required',
                'numeric',
                'gt:0',
            ];
        }

        return $rules;
    }

    private function assertUniquePayComponentCodes(): void
    {
        $seen = [];

        foreach (['earningComponents' => $this->earningComponents, 'deductionComponents' => $this->deductionComponents] as $field => $rows) {
            foreach ($rows as $index => $row) {
                $code = trim((string) ($row['code'] ?? ''));
                if ($code === '') {
                    continue;
                }

                if (isset($seen[$code])) {
                    throw ValidationException::withMessages([
                        "{$field}.{$index}.code" => 'Each pay component can only be added once.',
                    ]);
                }

                $seen[$code] = true;
            }
        }

        if ($this->hostelAvailable && isset($seen['HOSTEL'])) {
            throw ValidationException::withMessages([
                'hostelPaidAmount' => 'Hostel is already selected as a deduction. Remove it there or clear Hostel available.',
            ]);
        }
    }

    /** @return list<string> */
    private function attachablePayComponentCodes(?ComponentKind $kind = null): array
    {
        return PayComponent::query()
            ->where('is_active', true)
            ->whereIn('method', [
                CalcMethod::FIXED->value,
                CalcMethod::PROPORTIONAL->value,
                CalcMethod::QUANTITY_RATE->value,
                CalcMethod::NIGHT_UNITS->value,
            ])
            ->when($kind !== null, fn ($q) => $q->where('kind', $kind->value))
            ->orderBy('display_order')
            ->orderBy('code')
            ->pluck('code')
            ->all();
    }

    /** @return array<string, mixed> */
    private function personData(): array
    {
        [$firstName, $lastName] = $this->splitFullName($this->fullName);

        $data = [
            'title' => null,
            'full_name' => trim($this->fullName),
            'first_name' => $firstName,
            'middle_name' => null,
            'last_name' => $lastName,
            'name_with_initials' => $this->nameWithInitials,
            'calling_name' => $this->callingName,
            'date_of_birth' => $this->dateOfBirth,
            'gender' => $this->gender,
            'marital_status' => $this->maritalStatus,
            'nationality' => $this->nationality,
            'driving_licence_no' => $this->drivingLicenceNo !== '' ? $this->drivingLicenceNo : null,
            'landline_no' => $this->landlineNo !== '' ? $this->landlineNo : null,
            'office_no' => $this->officeNo !== '' ? $this->officeNo : null,
            'region' => $this->region,
            'arrive_work_from' => $this->arriveWorkFrom,
            'province' => $this->province,
            'electorate' => null,
            'polling_division' => null,
            'gn_division' => $this->gnDivision,
            'electorate_district' => $this->electorateDistrict,
            'distance_km' => $this->distanceKm !== '' ? $this->distanceKm : null,
        ];

        $data['addresses'] = [[
            'type' => 'PERMANENT',
            'line1' => $this->addressLine1,
            'line2' => null,
            'city' => $this->city,
            'district' => null,
            'postal_code' => null,
        ]];

        $contacts = [];
        $known = $this->existingPersonId !== null
            ? Person::query()->findOrFail($this->existingPersonId)->contacts()->get()
            : collect();

        $wanted = [[ContactType::MOBILE, trim($this->mobile), true]];
        if (trim($this->email) !== '') {
            $wanted[] = [ContactType::EMAIL, trim($this->email), true];
        }

        foreach ($wanted as [$type, $value, $primary]) {
            $exists = $known->contains(fn ($c) => $c->type === $type && $c->value === $value);
            if (! $exists) {
                $contacts[] = ['type' => $type->value, 'value' => $value, 'is_primary' => $primary];
            }
        }

        if ($contacts !== []) {
            $data['contacts'] = $contacts;
        }

        return $data;
    }

    /** @return array<string, mixed> */
    private function employmentData(): array
    {
        return [
            'type' => $this->type,
            'hire_date' => $this->hireDate,
            'epf_liable' => ! $this->isInternForEpf(),
            'epf_no' => null,
            'hrmis_active' => $this->hrmisActive,
            'attendance_active' => $this->attendanceActive,
            'payroll_active' => $this->payrollActive,
            'hostel_available' => $this->hostelAvailable,
            'food_breakfast' => $this->foodBreakfast,
            'food_lunch' => $this->foodLunch,
            'food_dinner' => $this->foodDinner,
            'confirmation_due_date' => $this->type === EmploymentType::PROBATION->value && $this->confirmationDueDate !== ''
                ? $this->confirmationDueDate
                : null,
            'employee_category_id' => $this->employeeCategoryId === null || $this->employeeCategoryId === ''
                ? null
                : (int) $this->employeeCategoryId,
            'staff_work_category_id' => $this->staffWorkCategoryId === null || $this->staffWorkCategoryId === ''
                ? null
                : (int) $this->staffWorkCategoryId,
        ];
    }

    /** @return array<string, int|null> */
    private function assignmentData(): array
    {
        return [
            'org_unit_id' => (int) $this->orgUnitId,
            'designation_id' => (int) $this->designationId,
            'grade_id' => (int) $this->gradeId,
            'location_id' => (int) $this->locationId,
            'manager_employment_id' => null,
        ];
    }

    /** @return array<string, mixed> */
    private function bankData(): array
    {
        if ($this->isCash) {
            return ['effective_from' => $this->hireDate, 'is_cash' => true];
        }

        return [
            'effective_from' => $this->hireDate,
            'is_cash' => false,
            'bank_id' => (int) $this->bankId,
            'bank_branch_id' => (int) $this->bankBranchId,
            'account_no' => $this->accountNo,
            'account_name' => trim($this->accountName) !== '' ? $this->accountName : $this->nameWithInitials,
        ];
    }

    /** @return array<string, mixed> */
    private function compensationData(): array
    {
        $this->normalizeMoneyFields();

        return [
            'basic_salary' => $this->type === EmploymentType::INTERN->value ? '0.00' : $this->plainMoney($this->basicSalary),
            'ot_rate_mode' => $this->otRateMode,
            'ot_rate' => $this->plainMoney($this->otRate) !== '' ? $this->plainMoney($this->otRate) : null,
            'attendance_tier_code' => trim($this->attendanceTierCode) !== '' ? trim($this->attendanceTierCode) : null,
            'change_reason' => 'Hire',
        ];
    }

    private function assertFingerprintAvailable(): void
    {
        if (! $this->attendanceActive) {
            return;
        }

        $group = trim($this->deviceGroup);
        $deviceUserId = $this->deviceUserId === null || $this->deviceUserId === '' ? null : (int) $this->deviceUserId;
        if ($group === '' || $deviceUserId === null) {
            return;
        }

        try {
            app(EnrolmentService::class)->assertAvailable($group, $deviceUserId, $this->hireDate);
        } catch (DeviceUserIdInUse $e) {
            throw ValidationException::withMessages([
                'deviceUserId' => $e->getMessage(),
            ]);
        }
    }

    private function normalizeMoneyFields(): void
    {
        $this->basicSalary = $this->plainMoney($this->basicSalary);
        $this->otRate = $this->plainMoney($this->otRate);
        $this->hostelPaidAmount = $this->plainMoney($this->hostelPaidAmount);
        $this->earningComponents = $this->plainMoneyRows($this->earningComponents);
        $this->deductionComponents = $this->plainMoneyRows($this->deductionComponents);
    }

    private function reformatMoneyFields(): void
    {
        $this->basicSalary = $this->formatMoneyDisplay($this->basicSalary);
        $this->otRate = $this->formatMoneyDisplay($this->otRate);
        $this->hostelPaidAmount = $this->formatMoneyDisplay($this->hostelPaidAmount);
        $this->earningComponents = $this->formatMoneyRows($this->earningComponents);
        $this->deductionComponents = $this->formatMoneyRows($this->deductionComponents);
    }

    private function plainMoney(string $value): string
    {
        return str_replace(',', '', trim($value));
    }

    /** Fixed two-decimal display for review / after validate. */
    private function formatMoneyDisplay(string $value): string
    {
        $plain = $this->plainMoney($value);
        if ($plain === '' || ! is_numeric($plain)) {
            return $plain;
        }

        return number_format((float) $plain, 2, '.', ',');
    }

    /** Thousand commas while typing; keeps a trailing decimal point and up to two fraction digits. */
    private function formatMoneyLive(string $value): string
    {
        $trimmed = trim($value);
        if ($trimmed === '') {
            return '';
        }

        $plain = $this->plainMoney($trimmed);
        if ($plain === '.') {
            return '0.';
        }

        if (! preg_match('/^\d*\.?\d{0,2}$/', $plain)) {
            $plain = preg_replace('/[^\d.]/', '', $plain) ?? '';
            $parts = explode('.', $plain, 2);
            $plain = $parts[0];
            if (isset($parts[1])) {
                $plain .= '.'.substr($parts[1], 0, 2);
            }
        }

        if ($plain === '' || $plain === '.') {
            return $plain === '.' ? '0.' : '';
        }

        $hasDot = str_contains($plain, '.');
        [$whole, $fraction] = array_pad(explode('.', $plain, 2), 2, null);
        $wholeDigits = preg_replace('/\D/', '', (string) $whole) ?? '';
        if ($wholeDigits === '') {
            $wholeDigits = '0';
        }

        $formatted = number_format((int) $wholeDigits, 0, '.', ',');
        if ($hasDot) {
            $formatted .= '.'.substr((string) ($fraction ?? ''), 0, 2);
        }

        return $formatted;
    }

    /**
     * @param  list<array{code: string, amount: string}>  $rows
     * @return list<array{code: string, amount: string}>
     */
    private function plainMoneyRows(array $rows): array
    {
        return array_map(function (array $row) {
            $row['amount'] = $this->plainMoney((string) ($row['amount'] ?? ''));

            return $row;
        }, $rows);
    }

    /**
     * @param  list<array{code: string, amount: string}>  $rows
     * @return list<array{code: string, amount: string}>
     */
    private function formatMoneyRows(array $rows): array
    {
        return array_map(function (array $row) {
            $row['amount'] = $this->formatMoneyDisplay((string) ($row['amount'] ?? ''));

            return $row;
        }, $rows);
    }

    private function setConfirmationDueDate(): void
    {
        if ($this->type !== EmploymentType::PROBATION->value) {
            $this->confirmationDueDate = '';

            return;
        }

        try {
            $this->confirmationDueDate = CarbonImmutable::createFromFormat('Y-m-d', $this->hireDate)->addMonths(6)->toDateString();
        } catch (\Throwable) {
            $this->confirmationDueDate = '';
        }
    }

    /** Shows a service error at the top of the review step, and on the field when it is obvious. */
    private function failWith(string $message): void
    {
        $this->domainError = $message;
        $this->step = 5;

        $field = match (true) {
            str_contains($message, 'Device user ID') => 'deviceUserId',
            str_contains($message, 'Hire date') => 'hireDate',
            str_contains($message, 'Basic salary') || str_contains($message, 'intern must have a basic salary') => 'basicSalary',
            str_contains($message, 'OT rate') => 'otRate',
            str_contains($message, 'attendance tier') => 'attendanceTierCode',
            default => null,
        };

        if ($field !== null) {
            $this->addError($field, $message);
        }
    }

    public function render(): View
    {
        $companyId = $this->companyId === null || $this->companyId === '' ? null : (int) $this->companyId;

        $options = $this->assignmentOptions($companyId);

        return view('corehr::livewire.hire-wizard', [
            ...$options,
            ...$this->bankOptions(),
            'types' => EmploymentType::cases(),
            'genders' => Gender::cases(),
            'maritalStatuses' => MaritalStatus::cases(),
            'nationalities' => self::NATIONALITIES,
            'provinces' => SriLankaGeo::provinces(),
            'electorateDistricts' => $this->province !== ''
                ? SriLankaGeo::districtsForProvince($this->province)
                : SriLankaGeo::districts(),
            'gnDivisionSuggestions' => GnDivision::namesForDistrictName($this->electorateDistrict),
            'relationships' => Relationship::activeNames(),
            'regions' => Region::activeNames(),
            'arriveWorkFromOptions' => ArriveWorkFrom::cases(),
            'employeeCategories' => EmployeeCategory::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'code']),
            'staffWorkCategories' => StaffWorkCategory::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'code']),
            'shifts' => Shift::query()->where('is_active', true)->orderBy('code')->get(['id', 'code', 'name']),
            'otModes' => OtMode::cases(),
            'otRateModes' => OtRateMode::cases(),
            'departments' => $companyId === null ? [] : OrgUnitOptions::departments($companyId),
            'sectionOptions' => $companyId === null || $this->departmentId === null || $this->departmentId === ''
                ? []
                : OrgUnitOptions::underDepartment((int) $this->departmentId, $companyId),
            'attendanceTiers' => AttendanceAllowanceTier::query()
                ->orderBy('code')
                ->get(['code', 'base_amount'])
                ->unique('code')
                ->values()
                ->map(fn (AttendanceAllowanceTier $tier) => [
                    'code' => $tier->code,
                    'label' => $tier->code.' — '.number_format((float) $tier->base_amount, 0, '.', ','),
                ]),
            'attachableEarningComponents' => $this->attachablePayComponents(ComponentKind::EARNING),
            'attachableDeductionComponents' => $this->attachablePayComponents(ComponentKind::DEDUCTION),
            'deviceGroups' => DeviceGroup::query()
                ->where('is_active', true)
                ->orderBy('code')
                ->get(['code', 'name'])
                ->map(fn (DeviceGroup $group) => [
                    'id' => $group->code,
                    'label' => $group->code.' — '.$group->name,
                ])
                ->values()
                ->all(),
            'employeeNoPreview' => $this->employeeNoPreview(),
            'epfNoPreview' => $this->epfNoPreview(),
            'isInternForEpf' => $this->isInternForEpf(),
            'plannedResignDate' => $this->plannedResignDate(),
            'review' => $this->step === 5 ? $this->reviewData() : [],
        ])->layout('components.layouts.module', [
            'moduleName' => 'Employees',
            'title' => 'Add employee',
            'moduleNav' => EmployeesNav::items('hire'),
        ]);
    }

    public function employeeNoPreview(): string
    {
        if ($this->companyId === null || $this->companyId === '') {
            return '—';
        }

        $company = Company::query()->find((int) $this->companyId);
        if ($company === null) {
            return '—';
        }

        return app(EmployeeNumberGenerator::class)->peek($company);
    }

    public function epfNoPreview(): string
    {
        if ($this->isInternForEpf()) {
            return '0';
        }

        if ($this->companyId === null || $this->companyId === '') {
            return '—';
        }

        $company = Company::query()->find((int) $this->companyId);
        if ($company === null) {
            return '—';
        }

        return app(EpfNumberGenerator::class)->peek($company);
    }

    public function plannedResignDate(): string
    {
        if ($this->dateOfBirth === '') {
            return '—';
        }

        try {
            return CarbonImmutable::createFromFormat('Y-m-d', $this->dateOfBirth)
                ->addYears(60)
                ->toDateString();
        } catch (\Throwable) {
            return '—';
        }
    }

    /** @return array{0: string, 1: string} */
    private function splitFullName(string $fullName): array
    {
        $parts = preg_split('/\s+/', trim($fullName), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if ($parts === []) {
            return ['', ''];
        }

        if (count($parts) === 1) {
            return [$parts[0], $parts[0]];
        }

        return [$parts[0], implode(' ', array_slice($parts, 1))];
    }

    private function initialsFromFullName(string $fullName): string
    {
        $parts = preg_split('/\s+/', trim($fullName), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if ($parts === []) {
            return '';
        }

        if (count($parts) === 1) {
            return $parts[0];
        }

        $last = array_pop($parts);
        $initials = [];
        foreach ($parts as $part) {
            $initials[] = mb_strtoupper(mb_substr($part, 0, 1)).'.';
        }

        return implode(' ', $initials).' '.$last;
    }

    private function ensureDefaultGradeAndLocation(): void
    {
        if ($this->gradeId === null || $this->gradeId === '') {
            $this->gradeId = Grade::query()->where('is_active', true)->orderBy('rank')->value('id');
        }

        if ($this->locationId === null || $this->locationId === '') {
            $this->locationId = Location::query()->where('is_active', true)->orderBy('name')->value('id');
        }
    }

    /** @return array<string, string> */
    private function reviewData(): array
    {
        $name = fn (string $model, mixed $id) => $id === null || $id === '' ? '—' : ($model::query()->find((int) $id)?->name ?? '—');

        return [
            'Company' => $name(Company::class, $this->companyId),
            'Mode' => $this->mode === 'REJOIN' ? 'Rejoin' : 'New hire',
            'NIC' => trim($this->nic) !== '' ? trim($this->nic) : '—',
            'Passport no.' => trim($this->passportNo) !== '' ? trim($this->passportNo) : '—',
            'Full name' => trim($this->fullName) !== '' ? trim($this->fullName) : '—',
            'Name with initials' => $this->nameWithInitials,
            'Calling name' => $this->callingName !== '' ? $this->callingName : '—',
            'Date of birth' => $this->dateOfBirth,
            'Planned resign date' => $this->plannedResignDate(),
            'Gender' => ucfirst(strtolower($this->gender)),
            'Nationality' => $this->nationality !== '' ? $this->nationality : '—',
            'Driving licence no.' => $this->drivingLicenceNo !== '' ? $this->drivingLicenceNo : '—',
            'Landline no.' => $this->landlineNo !== '' ? $this->landlineNo : '—',
            'Office no.' => $this->officeNo !== '' ? $this->officeNo : '—',
            'Region' => $this->region !== '' ? $this->region : '—',
            'Arrive work from' => $this->arriveWorkFrom !== ''
                ? (ArriveWorkFrom::tryFrom($this->arriveWorkFrom)?->label() ?? $this->arriveWorkFrom)
                : '—',
            'Mobile' => $this->mobile,
            'Email' => $this->email !== '' ? $this->email : '—',
            'Permanent address' => implode(', ', array_filter([$this->addressLine1, $this->city])),
            'Province' => $this->province !== '' ? $this->province : '—',
            'Electorate district' => $this->electorateDistrict !== '' ? $this->electorateDistrict : '—',
            'GN division' => $this->gnDivision !== '' ? $this->gnDivision : '—',
            'Distance (km)' => $this->distanceKm !== '' ? $this->distanceKm : '—',
            'Emergency contacts' => $this->emergencyContactsSummary(),
            'Employee no.' => $this->employeeNoPreview(),
            'Type' => ucfirst(strtolower($this->type)),
            'Hire date' => $this->hireDate,
            'Confirmation due' => $this->confirmationDueDate !== '' ? $this->confirmationDueDate : '—',
            'EPF' => $this->employmentData()['epf_liable'] ? 'Liable · '.$this->epfNoPreview() : 'Not liable',
            'HRMIS active' => $this->hrmisActive ? 'Yes' : 'No',
            'Attendance active' => $this->attendanceActive ? 'Yes' : 'No',
            'Payroll active' => $this->payrollActive ? 'Yes' : 'No',
            'Employee category' => $name(EmployeeCategory::class, $this->employeeCategoryId),
            'Office / shop category' => $name(StaffWorkCategory::class, $this->staffWorkCategoryId),
            'Department' => $name(OrgUnit::class, $this->departmentId),
            'Section / subsection' => $name(OrgUnit::class, $this->orgUnitId),
            'Designation' => $name(Designation::class, $this->designationId),
            'Basic salary' => $this->basicSalary !== '' ? $this->formatMoneyDisplay($this->basicSalary) : '—',
            'OT rate mode' => $this->otRateMode,
            'Fixed OT rate' => $this->otRateMode === OtRateMode::FIXED->value && $this->otRate !== ''
                ? $this->formatMoneyDisplay($this->otRate)
                : '—',
            'Attendance tier' => $this->attendanceTierCode !== '' ? $this->attendanceTierCode : '—',
            'Earnings' => $this->payComponentsSummary($this->earningComponents),
            'Deductions' => $this->payComponentsSummary($this->deductionComponents),
            'Hostel available' => $this->hostelAvailable ? 'Yes' : 'No',
            'Hostel paid amount' => $this->hostelAvailable && $this->hostelPaidAmount !== ''
                ? $this->formatMoneyDisplay($this->hostelPaidAmount)
                : '—',
            'Meals' => $this->mealsSummary(),
            'Payment' => $this->isCash ? 'Cash' : 'Bank account '.$this->accountNo,
            'Fingerprint' => $this->attendanceActive && trim($this->deviceGroup) !== ''
                ? trim($this->deviceGroup).' · ID '.$this->deviceUserId
                : ($this->attendanceActive ? 'Required' : 'Not enrolled (attendance inactive)'),
        ];
    }

    /** @return list<array{name: string, relationship: string, phone: string}> */
    private function filledEmergencyContacts(): array
    {
        $filled = [];

        foreach ($this->emergencyContacts as $row) {
            $name = trim((string) ($row['name'] ?? ''));
            $relationship = trim((string) ($row['relationship'] ?? ''));
            $phone = trim((string) ($row['phone'] ?? ''));

            if ($name === '' && $relationship === '' && $phone === '') {
                continue;
            }

            $filled[] = [
                'name' => $name,
                'relationship' => $relationship,
                'phone' => $phone,
            ];
        }

        return $filled;
    }

    /** @return list<array{code: string, amount: string}> */
    private function filledPayComponents(): array
    {
        $filled = array_merge(
            $this->filledPayComponentRows($this->earningComponents),
            $this->filledPayComponentRows($this->deductionComponents),
        );

        if ($this->hostelAvailable) {
            $plain = $this->plainMoney($this->hostelPaidAmount);
            $filled[] = [
                'code' => 'HOSTEL',
                'amount' => $plain !== '' && is_numeric($plain)
                    ? number_format((float) $plain, 2, '.', '')
                    : $plain,
            ];
        }

        return $filled;
    }

    private function mealsSummary(): string
    {
        $meals = array_values(array_filter([
            $this->foodBreakfast ? 'Breakfast' : null,
            $this->foodLunch ? 'Lunch' : null,
            $this->foodDinner ? 'Dinner' : null,
        ]));

        return $meals === [] ? 'None' : implode(', ', $meals);
    }

    /**
     * @param  list<array{code: string, amount: string}>  $rows
     * @return list<array{code: string, amount: string}>
     */
    private function filledPayComponentRows(array $rows): array
    {
        $filled = [];

        foreach ($rows as $row) {
            $code = trim((string) ($row['code'] ?? ''));
            $amount = trim((string) ($row['amount'] ?? ''));

            if ($code === '' && $amount === '') {
                continue;
            }

            $plain = $this->plainMoney($amount);

            $filled[] = [
                'code' => $code,
                'amount' => $plain !== '' && is_numeric($plain)
                    ? number_format((float) $plain, 2, '.', '')
                    : $plain,
            ];
        }

        return $filled;
    }

    /** @return Collection<int, PayComponent> */
    private function attachablePayComponents(ComponentKind $kind)
    {
        return PayComponent::query()
            ->where('is_active', true)
            ->where('kind', $kind->value)
            ->whereIn('method', [
                CalcMethod::FIXED->value,
                CalcMethod::PROPORTIONAL->value,
                CalcMethod::QUANTITY_RATE->value,
                CalcMethod::NIGHT_UNITS->value,
            ])
            ->orderBy('display_order')
            ->orderBy('code')
            ->get(['code', 'name', 'kind']);
    }

    private function emergencyContactsSummary(): string
    {
        $filled = $this->filledEmergencyContacts();
        if ($filled === []) {
            return '—';
        }

        return implode('; ', array_map(
            fn (array $row) => "{$row['name']} ({$row['relationship']}) {$row['phone']}",
            $filled,
        ));
    }

    /**
     * @param  list<array{code: string, amount: string}>  $rows
     */
    private function payComponentsSummary(array $rows): string
    {
        $filled = $this->filledPayComponentRows($rows);
        if ($filled === []) {
            return '—';
        }

        $names = PayComponent::query()
            ->whereIn('code', array_column($filled, 'code'))
            ->pluck('name', 'code');

        return implode('; ', array_map(
            fn (array $row) => ($names[$row['code']] ?? $row['code']).' '.$this->formatMoneyDisplay($row['amount']),
            $filled,
        ));
    }
}
