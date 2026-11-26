<?php

namespace Modules\CoreHr\Livewire\Tabs;

use App\Exceptions\Domain\DomainException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Component;
use Livewire\WithFileUploads;
use Modules\CoreHr\Enums\ArriveWorkFrom;
use Modules\CoreHr\Enums\ContactType;
use Modules\CoreHr\Enums\MaritalStatus;
use Modules\CoreHr\Livewire\Concerns\RejectsResignedMutations;
use Modules\CoreHr\Livewire\Concerns\SubmitsEmploymentRequests;
use Modules\CoreHr\Models\Employment;
use Modules\CoreHr\Models\GnDivision;
use Modules\CoreHr\Models\PersonContact;
use Modules\CoreHr\Models\PersonDependent;
use Modules\CoreHr\Models\PersonEmergencyContact;
use Modules\CoreHr\Models\PersonExperience;
use Modules\CoreHr\Models\PersonQualification;
use Modules\CoreHr\Models\Region;
use Modules\CoreHr\Models\Relationship;
use Modules\CoreHr\Services\PersonPhotoService;
use Modules\CoreHr\Support\SriLankaGeo;

class Personal extends Component
{
    use RejectsResignedMutations;
    use SubmitsEmploymentRequests;
    use WithFileUploads;

    private const ADDRESS_TYPES = ['PERMANENT'];

    private const CHILD_KINDS = ['emergency', 'dependent', 'qualification', 'experience'];

    public Employment $employment;

    public string $fullName = '';

    public string $nameWithInitials = '';

    public string $callingName = '';

    public string $maritalStatus = '';

    public string $nationality = '';

    public string $drivingLicenceNo = '';

    public string $landlineNo = '';

    public string $officeNo = '';

    public string $region = '';

    public string $arriveWorkFrom = '';

    public string $province = '';

    public string $electorateDistrict = '';

    public string $gnDivision = '';

    public string $distanceKm = '';

    /** @var array<string, array{line1: string, line2: string, city: string, district: string, postal_code: string}> */
    public array $addresses = [];

    public string $newContactType = 'MOBILE';

    public string $newContactValue = '';

    public bool $newContactPrimary = false;

    public bool $showContactForm = false;

    public ?int $editingContactId = null;

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

    /** @var mixed */
    public $photo = null;

    public function mount(Employment $employment): void
    {
        $this->employment = $employment;
        $this->flashOnParent = false;
        $this->fillFromPerson();
    }

    public function save(): void
    {
        $this->authorize('employee.update');
        $this->ensureNotResigned();
        $this->flashOnParent = false;

        $this->validate([
            'fullName' => ['required', 'string', 'max:150'],
            'nameWithInitials' => ['required', 'string', 'max:150'],
            'callingName' => ['nullable', 'string', 'max:100'],
            'maritalStatus' => ['nullable', Rule::enum(MaritalStatus::class)],
            'nationality' => ['nullable', 'string', 'max:50'],
            'drivingLicenceNo' => ['nullable', 'string', 'max:50'],
            'landlineNo' => ['nullable', 'string', 'max:20'],
            'officeNo' => ['nullable', 'string', 'max:20'],
            'region' => ['nullable', 'string', 'max:50', Rule::in(array_merge([''], Region::activeNames()))],
            'arriveWorkFrom' => ['nullable', Rule::enum(ArriveWorkFrom::class)],
            'province' => ['nullable', 'string', 'max:50', Rule::in(SriLankaGeo::provinces())],
            'electorateDistrict' => ['nullable', 'string', 'max:50', Rule::in(SriLankaGeo::districts())],
            'gnDivision' => ['nullable', 'string', 'max:100'],
            'distanceKm' => ['nullable', 'numeric', 'min:0'],
            'addresses.*.line1' => ['nullable', 'string', 'max:150'],
            'addresses.*.line2' => ['nullable', 'string', 'max:150'],
            'addresses.*.city' => ['nullable', 'string', 'max:80'],
        ]);

        [$firstName, $lastName] = $this->splitFullName($this->fullName);

        $person = [
            'title' => null,
            'full_name' => trim($this->fullName),
            'first_name' => $firstName,
            'middle_name' => null,
            'last_name' => $lastName,
            'name_with_initials' => $this->nameWithInitials,
            'calling_name' => $this->callingName !== '' ? $this->callingName : null,
        ];

        if ($this->maritalStatus !== '') {
            $person['marital_status'] = $this->maritalStatus;
        }
        if ($this->nationality !== '') {
            $person['nationality'] = $this->nationality;
        }
        $person['driving_licence_no'] = $this->drivingLicenceNo !== '' ? $this->drivingLicenceNo : null;
        $person['landline_no'] = $this->landlineNo !== '' ? $this->landlineNo : null;
        $person['office_no'] = $this->officeNo !== '' ? $this->officeNo : null;
        $person['region'] = $this->region !== '' ? $this->region : null;
        $person['arrive_work_from'] = $this->arriveWorkFrom !== '' ? $this->arriveWorkFrom : null;
        $person['province'] = $this->province !== '' ? $this->province : null;
        $person['electorate_district'] = $this->electorateDistrict !== '' ? $this->electorateDistrict : null;
        $person['gn_division'] = $this->gnDivision !== '' ? $this->gnDivision : null;
        $person['distance_km'] = $this->distanceKm !== '' ? $this->distanceKm : null;

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
                'line2' => null,
                'city' => $city,
                'district' => null,
                'postal_code' => null,
            ];
        }
        if ($addresses !== []) {
            $person['addresses'] = $addresses;
        }

        if ($this->submitRequest('UPDATE_PERSON', ['person' => $person, 'reason' => 'Personal details update'])) {
            if ($this->appliedAtOnce) {
                $this->fillFromPerson();
            }
        }
    }

    public function openAddContact(): void
    {
        $this->authorize('employee.update');
        $this->ensureNotResigned();
        $this->resetContactForm();
        $this->showContactForm = true;
    }

    public function openEditContact(int $id): void
    {
        $this->authorize('employee.update');
        $this->ensureNotResigned();
        $contact = $this->findContact($id);
        $this->resetContactForm();
        $this->editingContactId = $contact->id;
        $this->newContactType = $contact->type->value;
        $this->newContactValue = $contact->value;
        $this->newContactPrimary = (bool) $contact->is_primary;
        $this->showContactForm = true;
    }

    public function closeContactForm(): void
    {
        $this->showContactForm = false;
        $this->resetContactForm();
    }

    public function saveContact(): void
    {
        $this->authorize('employee.update');
        $this->ensureNotResigned();

        $this->validate([
            'newContactType' => ['required', Rule::enum(ContactType::class)],
            'newContactValue' => ['required', 'string', 'max:150'],
            'newContactPrimary' => ['boolean'],
        ]);

        $contact = [
            'type' => $this->newContactType,
            'value' => trim($this->newContactValue),
            'is_primary' => $this->newContactPrimary,
        ];
        if ($this->editingContactId !== null) {
            $contact['id'] = $this->editingContactId;
        }

        if ($this->submitRequest('UPSERT_CONTACT', ['contact' => $contact])) {
            $this->closeContactForm();
        }
    }

    public function removeContact(int $id): void
    {
        $this->authorize('employee.update');
        $this->ensureNotResigned();
        $this->findContact($id);
        $this->submitRequest('REMOVE_CONTACT', ['contact_id' => $id]);
    }

    private function resetContactForm(): void
    {
        $this->editingContactId = null;
        $this->newContactType = 'MOBILE';
        $this->newContactValue = '';
        $this->newContactPrimary = false;
        $this->resetValidation(['newContactType', 'newContactValue', 'newContactPrimary']);
    }

    private function findContact(int $id): PersonContact
    {
        return PersonContact::query()
            ->where('person_id', $this->employment->person_id)
            ->findOrFail($id);
    }

    public function uploadPhoto(PersonPhotoService $photos): void
    {
        $this->authorize('employee.update');
        $this->ensureNotResigned();
        $this->flashOnParent = false;
        $this->message = '';
        $this->errorMessage = '';

        $this->validate([
            'photo' => ['required', 'image', 'mimes:jpg,jpeg,png', 'max:2048'],
        ]);

        try {
            $photos->store($this->employment->person()->firstOrFail(), $this->photo);
        } catch (DomainException $e) {
            $this->errorMessage = $e->getMessage();

            return;
        }

        $this->reset('photo');
        $this->employment = $this->employment->fresh(['person']) ?? $this->employment;
        $this->message = 'Photo saved.';
        $this->dispatch('employment-updated', message: '');
    }

    public function removePhoto(PersonPhotoService $photos): void
    {
        $this->authorize('employee.update');
        $this->ensureNotResigned();
        $this->flashOnParent = false;
        $this->message = '';
        $this->errorMessage = '';

        $photos->delete($this->employment->person()->firstOrFail());
        $this->employment = $this->employment->fresh(['person']) ?? $this->employment;
        $this->message = 'Photo removed.';
        $this->dispatch('employment-updated', message: '');
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
        $this->ensureNotResigned();
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
        $this->ensureNotResigned();
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
        $this->ensureNotResigned();
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
        $this->ensureNotResigned();
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
        $this->ensureNotResigned();

        if (! in_array($this->childKind, self::CHILD_KINDS, true)) {
            return;
        }

        $payload = match ($this->childKind) {
            'emergency' => $this->validatedEmergency(),
            'dependent' => $this->validatedDependent(),
            'qualification' => $this->validatedQualification(),
            'experience' => $this->validatedExperience(),
        };

        if ($this->editingChildId !== null) {
            $payload['id'] = $this->editingChildId;
        }

        if ($this->submitRequest('UPSERT_CHILD', ['kind' => $this->childKind, 'child' => $payload])) {
            $this->closeChildForm();
        }
    }

    public function removeChild(string $kind, int $id): void
    {
        $this->authorize('employee.update');
        $this->ensureNotResigned();

        if (! in_array($kind, self::CHILD_KINDS, true)) {
            return;
        }

        $this->findChild($kind, $id);
        $this->submitRequest('REMOVE_CHILD', ['kind' => $kind, 'child_id' => $id]);
    }

    private function openAdd(string $kind): void
    {
        $this->authorize('employee.update');
        $this->ensureNotResigned();
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
            'childRelationship' => ['required', 'string', 'max:50', Rule::in(Relationship::activeNames())],
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
            'childRelationship' => ['required', 'string', 'max:50', Rule::in(Relationship::activeNames())],
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

    private function fillFromPerson(): void
    {
        $person = $this->employment->person()->with('addresses')->firstOrFail();

        $this->fullName = trim((string) ($person->full_name ?: implode(' ', array_filter([
            $person->first_name,
            $person->middle_name,
            $person->last_name,
        ]))));
        $this->nameWithInitials = $person->name_with_initials;
        $this->callingName = (string) $person->calling_name;
        $this->maritalStatus = $person->marital_status?->value ?? '';
        $this->nationality = (string) $person->nationality;
        $this->drivingLicenceNo = (string) $person->driving_licence_no;
        $this->landlineNo = (string) $person->landline_no;
        $this->officeNo = (string) $person->office_no;
        $this->region = (string) $person->region;
        $this->arriveWorkFrom = $person->arrive_work_from?->value ?? '';
        $this->province = (string) $person->province;
        $this->electorateDistrict = (string) $person->electorate_district;
        $this->gnDivision = (string) $person->gn_division;
        $this->distanceKm = $person->distance_km !== null ? (string) $person->distance_km : '';

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
            'provinces' => SriLankaGeo::provinces(),
            'districts' => SriLankaGeo::districts(),
            'electorateDistricts' => $this->province !== ''
                ? SriLankaGeo::districtsForProvince($this->province)
                : SriLankaGeo::districts(),
            'gnDivisionSuggestions' => GnDivision::namesForDistrictName($this->electorateDistrict),
            'relationships' => Relationship::activeNames(),
            'regions' => Region::activeNames(),
            'arriveWorkFromOptions' => ArriveWorkFrom::cases(),
            'canEdit' => auth()->user()->can('employee.update'),
        ]);
    }

    public function updatedProvince(): void
    {
        $allowed = SriLankaGeo::districtsForProvince($this->province);
        if ($this->electorateDistrict !== '' && ! in_array($this->electorateDistrict, $allowed, true)) {
            $this->electorateDistrict = '';
            $this->gnDivision = '';
        }
    }

    public function updatedElectorateDistrict(): void
    {
        $suggestions = GnDivision::namesForDistrictName($this->electorateDistrict);
        if ($this->gnDivision !== '' && $suggestions !== [] && ! in_array($this->gnDivision, $suggestions, true)) {
            $this->gnDivision = '';
        }
    }

    public function updatedFullName(): void
    {
        $this->nameWithInitials = $this->initialsFromFullName($this->fullName);
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
}
