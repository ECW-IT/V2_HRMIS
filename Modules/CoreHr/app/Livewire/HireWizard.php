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
use Modules\Attendance\Models\Device;
use Modules\Attendance\Services\EnrolmentService;
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
use Modules\CoreHr\Models\Person;
use Modules\CoreHr\Services\BankAccountService;
use Modules\CoreHr\Services\EmploymentService;
use Modules\CoreHr\Services\PersonService;
use Modules\CoreHr\Support\EmployeesNav;
use Modules\CoreHr\Support\Nic;
use Modules\Organisation\Models\Company;
use Modules\Organisation\Models\Grade;
use Modules\Organisation\Models\Job;
use Modules\Organisation\Models\Location;
use Modules\Organisation\Models\OrgUnit;

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

    // Step 2: personal
    public string $title = '';

    public string $firstName = '';

    public string $middleName = '';

    public string $lastName = '';

    public string $nameWithInitials = '';

    public string $callingName = '';

    public string $dateOfBirth = '';

    public string $gender = '';

    public string $maritalStatus = '';

    public string $nationality = 'Sri Lankan';

    public string $mobile = '';

    public string $email = '';

    public string $addressLine1 = '';

    public string $addressLine2 = '';

    public string $city = '';

    public string $district = '';

    public string $postalCode = '';

    public string $emergencyName = '';

    public string $emergencyRelationship = '';

    public string $emergencyPhone = '';

    // Step 3: employment
    public string $type = '';

    public string $hireDate = '';

    public string $confirmationDueDate = '';

    public bool $epfLiable = true;

    public string $epfNo = '';

    public string|int|null $orgUnitId = null;

    public string|int|null $jobId = null;

    public string|int|null $gradeId = null;

    public string|int|null $locationId = null;

    // Step 4: bank and fingerprint
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
        $permitted = CompanyContext::permittedIds();
        $selected = CompanyContext::id();

        $this->companyId = $selected ?? (count($permitted) === 1 ? $permitted[0] : null);
        $this->hireDate = CarbonImmutable::now(config('app.timezone'))->toDateString();
    }

    public function updatedType(): void
    {
        if ($this->type === EmploymentType::INTERN->value) {
            $this->epfLiable = false;
            $this->epfNo = '';
        } else {
            $this->epfLiable = true;
        }

        $this->setConfirmationDueDate();
    }

    public function updatedHireDate(): void
    {
        $this->setConfirmationDueDate();
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
    ): void {
        $this->authorize('employee.create');
        $this->domainError = '';

        foreach ([2, 3, 4] as $step) {
            $this->validateStep($step);
        }

        try {
            $employment = DB::transaction(function () use ($persons, $employments, $banks, $enrolments) {
                $personData = $this->personData();

                if ($this->existingPersonId !== null) {
                    $person = $persons->update(Person::query()->findOrFail($this->existingPersonId), $personData);
                } else {
                    $person = $persons->create($personData + [
                        'nic' => trim($this->nic) !== '' ? trim($this->nic) : null,
                        'passport_no' => trim($this->passportNo) !== '' ? trim($this->passportNo) : null,
                    ]);
                }

                if ($this->emergencyName !== '') {
                    $person->emergencyContacts()->create([
                        'name' => $this->emergencyName,
                        'relationship' => $this->emergencyRelationship,
                        'phone' => $this->emergencyPhone,
                    ]);
                }

                $company = Company::query()->findOrFail((int) $this->companyId);
                $employmentData = $this->employmentData();
                $assignment = $this->assignmentData();

                $employment = $this->mode === 'REJOIN'
                    ? $employments->rejoin($person, $company, $employmentData, $assignment)
                    : $employments->hire($person, $company, $employmentData, $assignment);

                $banks->set($employment, $this->bankData());

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
        $permitted = CompanyContext::permittedIds();

        $this->validate([
            'companyId' => ['required', 'integer', Rule::in($permitted)],
            'nic' => ['nullable', 'string', 'max:20'],
            'passportNo' => ['nullable', 'string', 'max:20'],
        ], [
            'companyId.required' => 'Choose a company.',
            'companyId.in' => 'Choose one of your companies.',
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
                'title', 'firstName', 'middleName', 'lastName', 'nameWithInitials', 'callingName', 'maritalStatus',
                'mobile', 'email', 'addressLine1', 'addressLine2', 'city', 'district', 'postalCode',
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

        $this->title = (string) $person->title;
        $this->firstName = $person->first_name;
        $this->middleName = (string) $person->middle_name;
        $this->lastName = $person->last_name;
        $this->nameWithInitials = $person->name_with_initials;
        $this->callingName = (string) $person->calling_name;
        $this->dateOfBirth = $person->date_of_birth->toDateString();
        $this->gender = $person->gender->value;
        $this->maritalStatus = $person->marital_status?->value ?? '';
        $this->nationality = (string) ($person->nationality ?: 'Sri Lankan');
        $this->identityLocked = $person->nic !== null;

        $mobile = $person->contacts->where('type', ContactType::MOBILE)->sortByDesc('is_primary')->first();
        $email = $person->contacts->where('type', ContactType::EMAIL)->sortByDesc('is_primary')->first();
        $this->mobile = (string) $mobile?->value;
        $this->email = (string) $email?->value;

        $address = $person->addresses->first(fn ($a) => $a->type->value === 'PERMANENT');
        $this->addressLine1 = (string) $address?->line1;
        $this->addressLine2 = (string) $address?->line2;
        $this->city = (string) $address?->city;
        $this->district = (string) $address?->district;
        $this->postalCode = (string) $address?->postal_code;
    }

    private function completePersonal(): void
    {
        $this->validateStep(2);
        $this->step = 3;
    }

    private function completeEmployment(): void
    {
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
            2 => [
                'title' => ['nullable', 'string', 'max:20'],
                'firstName' => ['required', 'string', 'max:100'],
                'middleName' => ['nullable', 'string', 'max:100'],
                'lastName' => ['required', 'string', 'max:100'],
                'nameWithInitials' => ['required', 'string', 'max:150'],
                'callingName' => ['nullable', 'string', 'max:100'],
                'dateOfBirth' => ['required', 'date_format:Y-m-d'],
                'gender' => ['required', Rule::enum(Gender::class)],
                'maritalStatus' => ['nullable', Rule::enum(MaritalStatus::class)],
                'nationality' => ['nullable', 'string', 'max:50'],
                'mobile' => ['required', 'string', 'regex:/^\+?[0-9][0-9\s-]{6,19}$/'],
                'email' => ['nullable', 'email', 'max:150'],
                'addressLine1' => ['required', 'string', 'max:150'],
                'addressLine2' => ['nullable', 'string', 'max:150'],
                'city' => ['required', 'string', 'max:80'],
                'district' => ['nullable', 'string', 'max:50'],
                'postalCode' => ['nullable', 'string', 'max:10'],
                'emergencyName' => ['nullable', 'string', 'max:150', 'required_with:emergencyRelationship,emergencyPhone'],
                'emergencyRelationship' => ['nullable', 'string', 'max:50', 'required_with:emergencyName,emergencyPhone'],
                'emergencyPhone' => ['nullable', 'string', 'max:20', 'required_with:emergencyName,emergencyRelationship'],
            ],
            3 => [
                'type' => ['required', Rule::enum(EmploymentType::class)],
                'hireDate' => ['required', 'date_format:Y-m-d'],
                'confirmationDueDate' => ['nullable', 'date_format:Y-m-d'],
                'epfLiable' => ['boolean'],
                'epfNo' => [
                    $this->epfLiable && $this->type !== EmploymentType::INTERN->value ? 'required' : 'nullable',
                    'string',
                    'max:20',
                ],
                'orgUnitId' => ['required', 'integer', Rule::exists('org_units', 'id')->where('company_id', (int) $this->companyId)],
                'jobId' => ['required', 'integer', Rule::exists('hr_jobs', 'id')],
                'gradeId' => ['required', 'integer', Rule::exists('grades', 'id')],
                'locationId' => ['required', 'integer', Rule::exists('locations', 'id')],
            ],
            4 => [
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
                'emergencyName.required_with' => 'Enter the name, relationship and phone of the emergency contact together.',
                'emergencyRelationship.required_with' => 'Enter the name, relationship and phone of the emergency contact together.',
                'emergencyPhone.required_with' => 'Enter the name, relationship and phone of the emergency contact together.',
            ],
            4 => [
                'deviceGroup.required_with' => 'Enter the device group as well.',
                'deviceUserId.required_with' => 'Enter the device user ID as well.',
            ],
            default => [],
        };
    }

    /** @return array<string, mixed> */
    private function personData(): array
    {
        $data = [
            'title' => $this->title !== '' ? $this->title : null,
            'first_name' => $this->firstName,
            'middle_name' => $this->middleName !== '' ? $this->middleName : null,
            'last_name' => $this->lastName,
            'name_with_initials' => $this->nameWithInitials,
            'calling_name' => $this->callingName !== '' ? $this->callingName : null,
            'date_of_birth' => $this->dateOfBirth,
            'gender' => $this->gender,
            'nationality' => $this->nationality !== '' ? $this->nationality : 'Sri Lankan',
        ];

        if ($this->maritalStatus !== '') {
            $data['marital_status'] = $this->maritalStatus;
        }

        $data['addresses'] = [[
            'type' => 'PERMANENT',
            'line1' => $this->addressLine1,
            'line2' => $this->addressLine2 !== '' ? $this->addressLine2 : null,
            'city' => $this->city,
            'district' => $this->district !== '' ? $this->district : null,
            'postal_code' => $this->postalCode !== '' ? $this->postalCode : null,
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
        $liable = ! $isIntern && $this->epfLiable;

        return [
            'type' => $this->type,
            'hire_date' => $this->hireDate,
            'epf_liable' => $liable,
            'epf_no' => $liable && trim($this->epfNo) !== '' ? trim($this->epfNo) : null,
            'confirmation_due_date' => $this->type === EmploymentType::PROBATION->value && $this->confirmationDueDate !== ''
                ? $this->confirmationDueDate
                : null,
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
            str_contains($message, 'EPF') => 'epfNo',
            str_contains($message, 'Device user ID') => 'deviceUserId',
            str_contains($message, 'Hire date') => 'hireDate',
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
            'companies' => Company::query()
                ->whereIn('id', CompanyContext::permittedIds())
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name']),
            'types' => EmploymentType::cases(),
            'genders' => Gender::cases(),
            'maritalStatuses' => MaritalStatus::cases(),
            'deviceGroups' => Device::query()->whereNotNull('device_group')->distinct()->orderBy('device_group')->pluck('device_group'),
            'selectedManager' => $this->selectedManager(),
            'managerResults' => $this->managerResults($companyId),
            'review' => $this->step === 5 ? $this->reviewData() : [],
        ])->layout('components.layouts.module', [
            'moduleName' => 'Employees',
            'title' => 'Hire employee',
            'moduleNav' => EmployeesNav::items('hire'),
        ]);
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
            'Name' => trim($this->title.' '.$this->firstName.' '.$this->middleName.' '.$this->lastName),
            'Name with initials' => $this->nameWithInitials,
            'Calling name' => $this->callingName !== '' ? $this->callingName : '—',
            'Date of birth' => $this->dateOfBirth,
            'Gender' => ucfirst(strtolower($this->gender)),
            'Mobile' => $this->mobile,
            'Email' => $this->email !== '' ? $this->email : '—',
            'Permanent address' => implode(', ', array_filter([$this->addressLine1, $this->addressLine2, $this->city, $this->district, $this->postalCode])),
            'Emergency contact' => $this->emergencyName !== '' ? "{$this->emergencyName} ({$this->emergencyRelationship}) {$this->emergencyPhone}" : '—',
            'Type' => ucfirst(strtolower($this->type)),
            'Hire date' => $this->hireDate,
            'Confirmation due' => $this->confirmationDueDate !== '' ? $this->confirmationDueDate : '—',
            'EPF' => $this->employmentData()['epf_liable'] ? 'Liable · '.($this->epfNo !== '' ? $this->epfNo : '—') : 'Not liable',
            'Department / section' => $name(OrgUnit::class, $this->orgUnitId),
            'Job' => $name(Job::class, $this->jobId),
            'Grade' => $name(Grade::class, $this->gradeId),
            'Location' => $name(Location::class, $this->locationId),
            'Reports to' => $manager ? $manager->employee_no.' · '.$manager->person->name_with_initials : '—',
            'Payment' => $this->isCash ? 'Cash' : 'Bank account '.$this->accountNo,
            'Fingerprint' => trim($this->deviceGroup) !== '' ? trim($this->deviceGroup).' · ID '.$this->deviceUserId : 'Not enrolled yet',
        ];
    }
}
