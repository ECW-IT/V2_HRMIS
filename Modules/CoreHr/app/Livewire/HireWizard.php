<?php

namespace Modules\CoreHr\Livewire;

use App\Exceptions\Domain\DomainException;
use App\Support\Company\CompanyContext;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Component;
use Modules\Attendance\Enums\AttendanceMode;
use Modules\Attendance\Enums\OtMode;
use Modules\Attendance\Models\Device;
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
use Modules\CoreHr\Livewire\Concerns\SearchesManagers;
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
use Modules\CoreHr\Support\SriLankaGeo;
use Modules\Organisation\Models\Company;
use Modules\Organisation\Models\EmployeeCategory;
use Modules\Organisation\Models\Grade;
use Modules\Organisation\Models\Job;
use Modules\Organisation\Models\Location;
use Modules\Organisation\Models\OrgUnit;
use Modules\Organisation\Models\StaffWorkCategory;
use Modules\Payroll\Enums\OtRateMode;
use Modules\Payroll\Models\AttendanceAllowanceTier;
use Modules\Payroll\Services\CompensationService;

class HireWizard extends Component
{
    use LoadsAssignmentOptions;
    use LoadsBankOptions;
    use SearchesManagers;

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

    public string|int|null $employeeCategoryId = null;

    public string|int|null $staffWorkCategoryId = null;

    public string|int|null $orgUnitId = null;

    public string|int|null $jobId = null;

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
            $this->epfLiable = true;
            if ($this->basicSalary === '0.00') {
                $this->basicSalary = '';
            }
        }

        $this->setConfirmationDueDate();
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

                $timeProfiles->set($employment, [
                    'effective_from' => $this->hireDate,
                    'shift_id' => (int) $this->shiftId,
                    'attendance_mode' => AttendanceMode::PUNCH->value,
                    'ot_mode' => $this->otMode,
                    'late_deduction' => $this->lateDeduction,
                ]);

                if (trim($this->deviceGroup) !== '') {
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
            $this->validate($this->rulesFor($step), $this->messagesFor($step));
        } catch (ValidationException $e) {
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
                'landlineNo' => ['nullable', 'string', 'max:20'],
                'officeNo' => ['nullable', 'string', 'max:20'],
                'region' => ['required', 'string', 'max:50', Rule::in(Region::activeNames())],
                'arriveWorkFrom' => ['required', Rule::enum(ArriveWorkFrom::class)],
                'province' => ['required', 'string', 'max:50', Rule::in(SriLankaGeo::provinces())],
                'electorateDistrict' => ['required', 'string', 'max:50', Rule::in(SriLankaGeo::districts())],
                'gnDivision' => $this->gnDivisionRules(),
                'distanceKm' => ['nullable', 'numeric', 'min:0'],
                'mobile' => ['required', 'string', 'regex:/^\+?[0-9][0-9\s-]{6,19}$/'],
                'email' => ['nullable', 'email', 'max:150'],
                'addressLine1' => ['required', 'string', 'max:150'],
                'city' => ['required', 'string', 'max:80'],
                'emergencyContacts' => ['required', 'array', 'min:1'],
            ], $this->emergencyContactRules()),
            3 => [
                'type' => ['required', Rule::enum(EmploymentType::class)],
                'hireDate' => ['required', 'date_format:Y-m-d'],
                'confirmationDueDate' => ['nullable', 'date_format:Y-m-d'],
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
                'orgUnitId' => ['required', 'integer', Rule::exists('org_units', 'id')->where('company_id', (int) $this->companyId)],
                'jobId' => ['required', 'integer', Rule::exists('hr_jobs', 'id')],
                'gradeId' => ['required', 'integer', Rule::exists('grades', 'id')],
                'locationId' => ['required', 'integer', Rule::exists('locations', 'id')],
                'shiftId' => ['required', 'integer', Rule::exists('shifts', 'id')->where('is_active', true)],
                'otMode' => ['required', Rule::enum(OtMode::class)],
                'lateDeduction' => ['boolean'],
            ],
            4 => [
                'basicSalary' => ['required', 'string', 'max:20'],
                'otRateMode' => ['required', Rule::enum(OtRateMode::class)],
                'otRate' => [
                    $this->otRateMode === OtRateMode::FIXED->value ? 'required' : 'nullable',
                    'string',
                    'max:20',
                ],
                'attendanceTierCode' => ['nullable', 'string', 'max:10'],
                'isCash' => ['boolean'],
                'bankId' => [$this->isCash ? 'nullable' : 'required', 'integer'],
                'bankBranchId' => [$this->isCash ? 'nullable' : 'required', 'integer'],
                'accountNo' => [$this->isCash ? 'nullable' : 'required', 'string', 'max:30'],
                'accountName' => [$this->isCash ? 'nullable' : 'required', 'string', 'max:150'],
                'deviceGroup' => ['nullable', 'string', 'max:20', 'required_with:deviceUserId'],
                'deviceUserId' => ['nullable', 'integer', 'min:1', 'required_with:deviceGroup'],
            ],
            default => [],
        };
    }

    /** @return array<string, string> */
    private function messagesFor(int $step): array
    {
        return match ($step) {
            2 => [
                'mobile.regex' => 'Enter a valid mobile number.',
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
                'emergencyContacts.*.name.required_with' => 'Enter the name, relationship and phone of the emergency contact together.',
                'emergencyContacts.*.relationship.required_with' => 'Enter the name, relationship and phone of the emergency contact together.',
                'emergencyContacts.*.phone.required_with' => 'Enter the name, relationship and phone of the emergency contact together.',
            ],
            3 => [
                'employeeCategoryId.required' => 'Select the employee category.',
            ],
            4 => [
                'basicSalary.required' => 'Enter the basic salary.',
                'otRate.required' => 'Enter the fixed OT rate.',
                'deviceGroup.required_with' => 'Enter the device group as well.',
                'deviceUserId.required_with' => 'Enter the device user ID as well.',
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
                $rules["emergencyContacts.{$index}.phone"] = ['required', 'string', 'max:20'];

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
                "required_with:emergencyContacts.{$index}.name,emergencyContacts.{$index}.relationship",
            ];
        }

        return $rules;
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
        $isIntern = $this->type === EmploymentType::INTERN->value;

        return [
            'type' => $this->type,
            'hire_date' => $this->hireDate,
            'epf_liable' => ! $isIntern,
            'epf_no' => null,
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
            'job_id' => (int) $this->jobId,
            'grade_id' => (int) $this->gradeId,
            'location_id' => (int) $this->locationId,
            'manager_employment_id' => $this->managerEmploymentId === null || $this->managerEmploymentId === ''
                ? null
                : (int) $this->managerEmploymentId,
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
        return [
            'basic_salary' => $this->type === EmploymentType::INTERN->value ? '0.00' : trim($this->basicSalary),
            'ot_rate_mode' => $this->otRateMode,
            'ot_rate' => trim($this->otRate) !== '' ? trim($this->otRate) : null,
            'attendance_tier_code' => trim($this->attendanceTierCode) !== '' ? trim($this->attendanceTierCode) : null,
            'change_reason' => 'Hire',
        ];
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
            'attendanceTiers' => AttendanceAllowanceTier::query()->orderBy('code')->pluck('code')->unique()->values(),
            'deviceGroups' => Device::query()->whereNotNull('device_group')->distinct()->orderBy('device_group')->pluck('device_group'),
            'selectedManager' => $this->selectedManager(),
            'managerResults' => $this->managerResults($companyId),
            'employeeNoPreview' => $this->employeeNoPreview(),
            'epfNoPreview' => $this->epfNoPreview(),
            'plannedResignDate' => $this->plannedResignDate(),
            'review' => $this->step === 5 ? $this->reviewData() : [],
        ])->layout('components.layouts.module', [
            'moduleName' => 'Employees',
            'title' => 'Hire employee',
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
        if ($this->type === EmploymentType::INTERN->value) {
            return '—';
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
        $manager = $this->selectedManager();

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
            'Employee category' => $name(EmployeeCategory::class, $this->employeeCategoryId),
            'Office / shop category' => $name(StaffWorkCategory::class, $this->staffWorkCategoryId),
            'Department / section' => $name(OrgUnit::class, $this->orgUnitId),
            'Designation' => $name(Job::class, $this->jobId),
            'Reports to' => $manager ? $manager->employee_no.' · '.$manager->person->name_with_initials : '—',
            'Basic salary' => $this->basicSalary !== '' ? $this->basicSalary : '—',
            'OT rate mode' => $this->otRateMode,
            'Fixed OT rate' => $this->otRateMode === OtRateMode::FIXED->value && $this->otRate !== '' ? $this->otRate : '—',
            'Attendance tier' => $this->attendanceTierCode !== '' ? $this->attendanceTierCode : '—',
            'Payment' => $this->isCash ? 'Cash' : 'Bank account '.$this->accountNo,
            'Fingerprint' => trim($this->deviceGroup) !== '' ? trim($this->deviceGroup).' · ID '.$this->deviceUserId : 'Not enrolled yet',
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
}
