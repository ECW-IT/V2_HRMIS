<?php

namespace Modules\CoreHr\Livewire\Tabs;

use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Component;
use Modules\CoreHr\Enums\ContactType;
use Modules\CoreHr\Enums\MaritalStatus;
use Modules\CoreHr\Livewire\Concerns\SubmitsEmploymentRequests;
use Modules\CoreHr\Models\Employment;

class Personal extends Component
{
    use SubmitsEmploymentRequests;

    private const ADDRESS_TYPES = ['PERMANENT', 'CURRENT'];

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
