<?php

namespace Modules\CoreHr\Livewire\Tabs;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Component;
use Modules\CoreHr\Enums\ContactType;
use Modules\CoreHr\Enums\MaritalStatus;
use Modules\CoreHr\Livewire\Concerns\SubmitsEmploymentRequests;
use Modules\CoreHr\Models\Employment;
use Modules\CoreHr\Models\Person;
use Modules\CoreHr\Models\PersonDependent;
use Modules\CoreHr\Models\PersonEmergencyContact;
use Modules\CoreHr\Models\PersonExperience;
use Modules\CoreHr\Models\PersonQualification;

class Personal extends Component
{
    use SubmitsEmploymentRequests;

    private const ADDRESS_TYPES = ['PERMANENT', 'CURRENT'];

    private const CHILD_KINDS = ['emergency', 'dependent', 'qualification', 'experience'];

    public Employment $employment;

    public string $title = '';

    public string $firstName = '';

    public string $middleName = '';

    public string $lastName = '';

    public string $nameWithInitials = '';

    public string $callingName = '';

    public string $maritalStatus = '';

    public string $nationality = '';

    /** @var array<string, array{line1: string, line2: string, city: string, district: string, postal_code: string}> */
    public array $addresses = [];

    public string $newContactType = 'MOBILE';

    public string $newContactValue = '';

    public bool $newContactPrimary = false;

    public bool $showChildForm = false;

    public string $childKind = '';

    public ?int $editingChildId = null;

    public string $childName = '';

    public string $childRelationship = '';

    public string $childPhone = '';

    public string $childAddress = '';

    public string $childDateOfBirth = '';

    public string $childType = '';

    public string $childInstitute = '';

    public string $childTitle = '';

    public string $childYear = '';

    public string $childEmployer = '';

    public string $childJobTitle = '';

    public string $childFromDate = '';

    public string $childToDate = '';

    public function mount(Employment $employment): void
    {
        $this->employment = $employment;
        $this->flashOnParent = false;
        $this->fillFromPerson();
    }

    public function save(): void
    {
        $this->authorize('employee.update');
        $this->flashOnParent = false;

        $this->validate([
            'title' => ['nullable', 'string', 'max:20'],
            'firstName' => ['required', 'string', 'max:100'],
            'middleName' => ['nullable', 'string', 'max:100'],
            'lastName' => ['required', 'string', 'max:100'],
            'nameWithInitials' => ['required', 'string', 'max:150'],
            'callingName' => ['nullable', 'string', 'max:100'],
            'maritalStatus' => ['nullable', Rule::enum(MaritalStatus::class)],
            'nationality' => ['nullable', 'string', 'max:50'],
            'addresses.*.line1' => ['nullable', 'string', 'max:150'],
            'addresses.*.line2' => ['nullable', 'string', 'max:150'],
            'addresses.*.city' => ['nullable', 'string', 'max:80'],
            'addresses.*.district' => ['nullable', 'string', 'max:50'],
            'addresses.*.postal_code' => ['nullable', 'string', 'max:10'],
            'newContactType' => ['required', Rule::enum(ContactType::class)],
            'newContactValue' => ['nullable', 'string', 'max:150'],
        ]);

        $person = [
            'title' => $this->title !== '' ? $this->title : null,
            'first_name' => $this->firstName,
            'middle_name' => $this->middleName !== '' ? $this->middleName : null,
            'last_name' => $this->lastName,
            'name_with_initials' => $this->nameWithInitials,
            'calling_name' => $this->callingName !== '' ? $this->callingName : null,
        ];

        if ($this->maritalStatus !== '') {
            $person['marital_status'] = $this->maritalStatus;
        }
        if ($this->nationality !== '') {
            $person['nationality'] = $this->nationality;
        }

        $addresses = [];
        foreach (self::ADDRESS_TYPES as $type) {
            $row = $this->addresses[$type] ?? [];
            $line1 = trim((string) ($row['line1'] ?? ''));
            $city = trim((string) ($row['city'] ?? ''));

            if ($line1 === '' && $city === '') {
                continue;
            }

            if ($line1 === '' || $city === '') {
                $this->addError("addresses.{$type}.".($line1 === '' ? 'line1' : 'city'), 'Enter both the first address line and the city.');

                return;
            }

            $addresses[] = [
                'type' => $type,
                'line1' => $line1,
                'line2' => ($row['line2'] ?? '') !== '' ? $row['line2'] : null,
                'city' => $city,
                'district' => ($row['district'] ?? '') !== '' ? $row['district'] : null,
                'postal_code' => ($row['postal_code'] ?? '') !== '' ? $row['postal_code'] : null,
            ];
        }
        if ($addresses !== []) {
            $person['addresses'] = $addresses;
        }

        if (trim($this->newContactValue) !== '') {
            $person['contacts'] = [[
                'type' => $this->newContactType,
                'value' => trim($this->newContactValue),
                'is_primary' => $this->newContactPrimary,
            ]];
        }

        if ($this->submitRequest('UPDATE_PERSON', ['person' => $person, 'reason' => 'Personal details update'])) {
            $this->newContactValue = '';
            $this->newContactPrimary = false;

            if ($this->appliedAtOnce) {
                $this->fillFromPerson();
            }
        }
    }

    public function openAddEmergency(): void
    {
        $this->openAdd('emergency');
    }

    public function openAddDependent(): void
    {
        $this->openAdd('dependent');
    }

    public function openAddQualification(): void
    {
        $this->openAdd('qualification');
    }

    public function openAddExperience(): void
    {
        $this->openAdd('experience');
    }

    public function openEditEmergency(int $id): void
    {
        $this->authorize('employee.update');
        $row = $this->findChild('emergency', $id);
        $this->resetChildForm();
        $this->childKind = 'emergency';
        $this->editingChildId = $row->id;
        $this->childName = $row->name;
        $this->childRelationship = $row->relationship;
        $this->childPhone = $row->phone;
        $this->childAddress = (string) $row->address;
        $this->showChildForm = true;
    }

    public function openEditDependent(int $id): void
    {
        $this->authorize('employee.update');
        $row = $this->findChild('dependent', $id);
        $this->resetChildForm();
        $this->childKind = 'dependent';
        $this->editingChildId = $row->id;
        $this->childName = $row->name;
        $this->childRelationship = $row->relationship;
        $this->childDateOfBirth = $row->date_of_birth?->format('Y-m-d') ?? '';
        $this->showChildForm = true;
    }

    public function openEditQualification(int $id): void
    {
        $this->authorize('employee.update');
        $row = $this->findChild('qualification', $id);
        $this->resetChildForm();
        $this->childKind = 'qualification';
        $this->editingChildId = $row->id;
        $this->childType = $row->type;
        $this->childInstitute = $row->institute;
        $this->childTitle = $row->title;
        $this->childYear = $row->year !== null ? (string) $row->year : '';
        $this->showChildForm = true;
    }

    public function openEditExperience(int $id): void
    {
        $this->authorize('employee.update');
        $row = $this->findChild('experience', $id);
        $this->resetChildForm();
        $this->childKind = 'experience';
        $this->editingChildId = $row->id;
        $this->childEmployer = $row->employer;
        $this->childJobTitle = $row->job_title;
        $this->childFromDate = $row->from_date->format('Y-m-d');
        $this->childToDate = $row->to_date?->format('Y-m-d') ?? '';
        $this->showChildForm = true;
    }

    public function closeChildForm(): void
    {
        $this->showChildForm = false;
        $this->resetChildForm();
    }

    public function saveChild(): void
    {
        $this->authorize('employee.update');

        if (! in_array($this->childKind, self::CHILD_KINDS, true)) {
            return;
        }

        $payload = match ($this->childKind) {
            'emergency' => $this->validatedEmergency(),
            'dependent' => $this->validatedDependent(),
            'qualification' => $this->validatedQualification(),
            'experience' => $this->validatedExperience(),
        };

        if ($this->editingChildId === null) {
            $this->person()->{$this->relationName($this->childKind)}()->create($payload);
            $this->message = 'Saved.';
        } else {
            $this->findChild($this->childKind, $this->editingChildId)->update($payload);
            $this->message = 'Saved.';
        }

        $this->errorMessage = '';
        $this->closeChildForm();
    }

    public function removeChild(string $kind, int $id): void
    {
        $this->authorize('employee.update');

        if (! in_array($kind, self::CHILD_KINDS, true)) {
            return;
        }

        $this->findChild($kind, $id)->delete();
        $this->message = 'Removed.';
        $this->errorMessage = '';
    }

    private function openAdd(string $kind): void
    {
        $this->authorize('employee.update');
        $this->resetChildForm();
        $this->childKind = $kind;
        $this->editingChildId = null;
        $this->showChildForm = true;
    }

    private function resetChildForm(): void
    {
        $this->resetValidation();
        $this->childKind = '';
        $this->editingChildId = null;
        $this->childName = '';
        $this->childRelationship = '';
        $this->childPhone = '';
        $this->childAddress = '';
        $this->childDateOfBirth = '';
        $this->childType = '';
        $this->childInstitute = '';
        $this->childTitle = '';
        $this->childYear = '';
        $this->childEmployer = '';
        $this->childJobTitle = '';
        $this->childFromDate = '';
        $this->childToDate = '';
    }

    /** @return array{name: string, relationship: string, phone: string, address: ?string} */
    private function validatedEmergency(): array
    {
        $validated = $this->validate([
            'childName' => ['required', 'string', 'max:150'],
            'childRelationship' => ['required', 'string', 'max:50'],
            'childPhone' => ['required', 'string', 'max:20'],
            'childAddress' => ['nullable', 'string', 'max:255'],
        ]);

        return [
            'name' => $validated['childName'],
            'relationship' => $validated['childRelationship'],
            'phone' => $validated['childPhone'],
            'address' => ($validated['childAddress'] ?? '') !== '' ? $validated['childAddress'] : null,
        ];
    }

    /** @return array{name: string, relationship: string, date_of_birth: ?string} */
    private function validatedDependent(): array
    {
        $validated = $this->validate([
            'childName' => ['required', 'string', 'max:150'],
            'childRelationship' => ['required', 'string', 'max:50'],
            'childDateOfBirth' => $this->childDateOfBirth === ''
                ? ['nullable']
                : ['date'],
        ]);

        return [
            'name' => $validated['childName'],
            'relationship' => $validated['childRelationship'],
            'date_of_birth' => $this->childDateOfBirth === '' ? null : $validated['childDateOfBirth'],
        ];
    }

    /** @return array{type: string, institute: string, title: string, year: ?int} */
    private function validatedQualification(): array
    {
        $rules = [
            'childType' => ['required', 'string', 'max:50'],
            'childInstitute' => ['required', 'string', 'max:150'],
            'childTitle' => ['required', 'string', 'max:150'],
            'childYear' => $this->childYear === ''
                ? ['nullable']
                : ['integer', 'min:1900', 'max:2100'],
        ];

        $validated = $this->validate($rules);

        return [
            'type' => $validated['childType'],
            'institute' => $validated['childInstitute'],
            'title' => $validated['childTitle'],
            'year' => $this->childYear === '' ? null : (int) $validated['childYear'],
        ];
    }

    /** @return array{employer: string, job_title: string, from_date: string, to_date: ?string} */
    private function validatedExperience(): array
    {
        $validated = $this->validate([
            'childEmployer' => ['required', 'string', 'max:150'],
            'childJobTitle' => ['required', 'string', 'max:150'],
            'childFromDate' => ['required', 'date'],
            'childToDate' => $this->childToDate === ''
                ? ['nullable']
                : ['date', 'after_or_equal:childFromDate'],
        ]);

        return [
            'employer' => $validated['childEmployer'],
            'job_title' => $validated['childJobTitle'],
            'from_date' => $validated['childFromDate'],
            'to_date' => $this->childToDate === '' ? null : $validated['childToDate'],
        ];
    }

    private function person(): Person
    {
        return $this->employment->person()->firstOrFail();
    }

    private function findChild(string $kind, int $id): Model
    {
        $class = match ($kind) {
            'emergency' => PersonEmergencyContact::class,
            'dependent' => PersonDependent::class,
            'qualification' => PersonQualification::class,
            'experience' => PersonExperience::class,
        };

        return $class::query()
            ->where('person_id', $this->employment->person_id)
            ->findOrFail($id);
    }

    private function relationName(string $kind): string
    {
        return match ($kind) {
            'emergency' => 'emergencyContacts',
            'dependent' => 'dependents',
            'qualification' => 'qualifications',
            'experience' => 'experience',
        };
    }

    private function fillFromPerson(): void
    {
        $person = $this->employment->person()->with('addresses')->firstOrFail();

        $this->title = (string) $person->title;
        $this->firstName = $person->first_name;
        $this->middleName = (string) $person->middle_name;
        $this->lastName = $person->last_name;
        $this->nameWithInitials = $person->name_with_initials;
        $this->callingName = (string) $person->calling_name;
        $this->maritalStatus = $person->marital_status?->value ?? '';
        $this->nationality = (string) $person->nationality;

        $this->addresses = [];
        foreach (self::ADDRESS_TYPES as $type) {
            $address = $person->addresses->first(fn ($a) => $a->type->value === $type);
            $this->addresses[$type] = [
                'line1' => (string) $address?->line1,
                'line2' => (string) $address?->line2,
                'city' => (string) $address?->city,
                'district' => (string) $address?->district,
                'postal_code' => (string) $address?->postal_code,
            ];
        }
    }

    public function render(): View
    {
        $this->authorize('employee.view');

        $person = $this->employment->person()->with([
            'contacts', 'emergencyContacts', 'dependents', 'qualifications', 'experience',
        ])->firstOrFail();

        return view('corehr::livewire.tabs.personal', [
            'person' => $person,
            'maritalStatuses' => MaritalStatus::cases(),
            'contactTypes' => ContactType::cases(),
            'canEdit' => auth()->user()->can('employee.update'),
        ]);
    }
}
