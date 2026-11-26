<?php

namespace Modules\CoreHr\Services;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Modules\CoreHr\Enums\AddressType;
use Modules\CoreHr\Enums\ArriveWorkFrom;
use Modules\CoreHr\Enums\ContactType;
use Modules\CoreHr\Enums\Gender;
use Modules\CoreHr\Enums\MaritalStatus;
use Modules\CoreHr\Exceptions\DuplicatePerson;
use Modules\CoreHr\Exceptions\InvalidNic;
use Modules\CoreHr\Exceptions\InvalidPersonData;
use Modules\CoreHr\Models\Person;
use Modules\CoreHr\Models\PersonContact;
use Modules\CoreHr\Support\Nic;

final class PersonService
{
    /** Keys as the persons columns; 'nic' may be in either format. Optional nested keys: contacts[], addresses[]. */
    public function create(array $data): Person
    {
        return DB::transaction(function () use ($data) {
            $payload = $this->preparePayload($data);
            $this->assertIdentityPresent($payload);
            $this->assertUniqueIdentity($payload['nic'], $payload['passport_no']);
            $this->assertNicDerivedFields($payload);
            $this->assertAge($payload['date_of_birth']);

            $person = Person::query()->create($payload);

            $this->syncNested($person, $data);

            return $person->fresh(['contacts', 'addresses']);
        });
    }

    public function update(Person $person, array $data): Person
    {
        return DB::transaction(function () use ($person, $data) {
            $merged = array_merge($person->only([
                'nic',
                'nic_as_entered',
                'passport_no',
                'title',
                'full_name',
                'first_name',
                'middle_name',
                'last_name',
                'name_with_initials',
                'calling_name',
                'date_of_birth',
                'gender',
                'marital_status',
                'nationality',
                'driving_licence_no',
                'landline_no',
                'office_no',
                'region',
                'arrive_work_from',
                'province',
                'electorate',
                'polling_division',
                'gn_division',
                'electorate_district',
                'distance_km',
                'photo_path',
                'nic_mismatch',
            ]), $data);

            $payload = $this->preparePayload($merged, $person);
            $this->assertIdentityPresent($payload);
            $this->assertUniqueIdentity($payload['nic'], $payload['passport_no'], $person->id);
            $this->assertNicDerivedFields($payload);
            $this->assertAge($payload['date_of_birth']);

            $person->update($payload);
            $this->syncNested($person, $data);

            return $person->fresh(['contacts', 'addresses']);
        });
    }

    /**
     * @param  array{id?: int|null, type: string, value: string, is_primary?: bool}  $data
     */
    public function upsertContact(Person $person, array $data): PersonContact
    {
        return DB::transaction(function () use ($person, $data) {
            $type = $data['type'] instanceof ContactType
                ? $data['type']
                : ContactType::from((string) $data['type']);
            $value = trim((string) $data['value']);
            $isPrimary = (bool) ($data['is_primary'] ?? false);
            $id = isset($data['id']) && $data['id'] !== null && $data['id'] !== ''
                ? (int) $data['id']
                : null;

            if ($isPrimary) {
                $person->contacts()
                    ->where('type', $type->value)
                    ->when($id !== null, fn ($query) => $query->where('id', '!=', $id))
                    ->where('is_primary', true)
                    ->update(['is_primary' => false]);
            }

            if ($id === null) {
                return $person->contacts()->create([
                    'type' => $type,
                    'value' => $value,
                    'is_primary' => $isPrimary,
                ]);
            }

            $contact = $person->contacts()->findOrFail($id);
            $contact->update([
                'type' => $type,
                'value' => $value,
                'is_primary' => $isPrimary,
            ]);

            return $contact->fresh();
        });
    }

    public function removeContact(Person $person, int $contactId): void
    {
        $person->contacts()->findOrFail($contactId)->delete();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function upsertChild(Person $person, string $kind, array $data): Model
    {
        return DB::transaction(function () use ($person, $kind, $data) {
            $relation = $this->childRelation($kind);
            $id = isset($data['id']) && $data['id'] !== null && $data['id'] !== ''
                ? (int) $data['id']
                : null;
            unset($data['id']);

            if ($id === null) {
                return $person->{$relation}()->create($data);
            }

            $row = $person->{$relation}()->findOrFail($id);
            $row->update($data);

            return $row->fresh();
        });
    }

    public function removeChild(Person $person, string $kind, int $id): void
    {
        $person->{$this->childRelation($kind)}()->findOrFail($id)->delete();
    }

    public function findByNic(string $raw): ?Person
    {
        try {
            $normalised = Nic::normalise($raw);
        } catch (InvalidNic) {
            return null;
        }

        return Person::query()->where('nic', $normalised)->first();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function preparePayload(array $data, ?Person $existing = null): array
    {
        $nicRaw = array_key_exists('nic', $data) ? $data['nic'] : $existing?->nic;
        $nicAsEntered = $existing?->nic_as_entered;
        $nic = $existing?->nic;

        if (array_key_exists('nic', $data)) {
            if ($data['nic'] === null || $data['nic'] === '') {
                $nic = null;
                $nicAsEntered = null;
            } else {
                $nicAsEntered = mb_strtoupper(trim((string) $data['nic']));
                $nic = Nic::normalise((string) $data['nic']);
            }
        }

        $gender = $data['gender'] ?? $existing?->gender;
        if (is_string($gender)) {
            $gender = Gender::from($gender);
        }

        $marital = $data['marital_status'] ?? $existing?->marital_status ?? null;
        if (is_string($marital)) {
            $marital = MaritalStatus::from($marital);
        }

        $arriveWorkFrom = array_key_exists('arrive_work_from', $data)
            ? $data['arrive_work_from']
            : $existing?->arrive_work_from;
        if ($arriveWorkFrom === '' || $arriveWorkFrom === null) {
            $arriveWorkFrom = null;
        } elseif (is_string($arriveWorkFrom)) {
            $arriveWorkFrom = ArriveWorkFrom::from($arriveWorkFrom);
        }

        $dob = $data['date_of_birth'] ?? $existing?->date_of_birth;
        if ($dob instanceof \DateTimeInterface) {
            $dob = CarbonImmutable::instance($dob)->toDateString();
        }

        return [
            'nic' => $nic,
            'nic_as_entered' => $nicAsEntered,
            'passport_no' => array_key_exists('passport_no', $data)
                ? (($data['passport_no'] === '' || $data['passport_no'] === null) ? null : $data['passport_no'])
                : $existing?->passport_no,
            'title' => $data['title'] ?? $existing?->title,
            'full_name' => array_key_exists('full_name', $data)
                ? (($data['full_name'] === '' || $data['full_name'] === null) ? null : $data['full_name'])
                : $existing?->full_name,
            'first_name' => $data['first_name'] ?? $existing->first_name,
            'middle_name' => array_key_exists('middle_name', $data) ? $data['middle_name'] : $existing?->middle_name,
            'last_name' => $data['last_name'] ?? $existing->last_name,
            'name_with_initials' => $data['name_with_initials'] ?? $existing->name_with_initials,
            'calling_name' => array_key_exists('calling_name', $data) ? $data['calling_name'] : $existing?->calling_name,
            'date_of_birth' => $dob,
            'gender' => $gender,
            'marital_status' => $marital,
            'nationality' => $data['nationality'] ?? $existing?->nationality ?? 'Sri Lankan',
            'driving_licence_no' => array_key_exists('driving_licence_no', $data)
                ? (($data['driving_licence_no'] === '' || $data['driving_licence_no'] === null) ? null : $data['driving_licence_no'])
                : $existing?->driving_licence_no,
            'landline_no' => array_key_exists('landline_no', $data)
                ? (($data['landline_no'] === '' || $data['landline_no'] === null) ? null : $data['landline_no'])
                : $existing?->landline_no,
            'office_no' => array_key_exists('office_no', $data)
                ? (($data['office_no'] === '' || $data['office_no'] === null) ? null : $data['office_no'])
                : $existing?->office_no,
            'region' => array_key_exists('region', $data)
                ? (($data['region'] === '' || $data['region'] === null) ? null : $data['region'])
                : $existing?->region,
            'arrive_work_from' => $arriveWorkFrom,
            'province' => array_key_exists('province', $data)
                ? (($data['province'] === '' || $data['province'] === null) ? null : $data['province'])
                : $existing?->province,
            'electorate' => array_key_exists('electorate', $data)
                ? (($data['electorate'] === '' || $data['electorate'] === null) ? null : $data['electorate'])
                : $existing?->electorate,
            'polling_division' => array_key_exists('polling_division', $data)
                ? (($data['polling_division'] === '' || $data['polling_division'] === null) ? null : $data['polling_division'])
                : $existing?->polling_division,
            'gn_division' => array_key_exists('gn_division', $data)
                ? (($data['gn_division'] === '' || $data['gn_division'] === null) ? null : $data['gn_division'])
                : $existing?->gn_division,
            'electorate_district' => array_key_exists('electorate_district', $data)
                ? (($data['electorate_district'] === '' || $data['electorate_district'] === null) ? null : $data['electorate_district'])
                : $existing?->electorate_district,
            'distance_km' => array_key_exists('distance_km', $data)
                ? (($data['distance_km'] === '' || $data['distance_km'] === null) ? null : $data['distance_km'])
                : $existing?->distance_km,
            'photo_path' => array_key_exists('photo_path', $data) ? $data['photo_path'] : $existing?->photo_path,
            'nic_mismatch' => $data['nic_mismatch'] ?? $existing?->nic_mismatch ?? false,
        ];
    }

    /** @param  array<string, mixed>  $payload */
    private function assertIdentityPresent(array $payload): void
    {
        if ($payload['nic'] === null && $payload['passport_no'] === null) {
            throw new InvalidPersonData('Enter a NIC or a passport number.');
        }
    }

    private function assertUniqueIdentity(?string $nic, ?string $passportNo, ?int $ignoreId = null): void
    {
        if ($nic !== null) {
            $existing = Person::query()
                ->where('nic', $nic)
                ->when($ignoreId !== null, fn ($q) => $q->where('id', '!=', $ignoreId))
                ->first();
            if ($existing !== null) {
                throw new DuplicatePerson($existing->id);
            }
        }

        if ($passportNo !== null) {
            $existing = Person::query()
                ->where('passport_no', $passportNo)
                ->when($ignoreId !== null, fn ($q) => $q->where('id', '!=', $ignoreId))
                ->first();
            if ($existing !== null) {
                throw new DuplicatePerson($existing->id);
            }
        }
    }

    /** @param  array<string, mixed>  $payload */
    private function assertNicDerivedFields(array $payload): void
    {
        if ($payload['nic'] === null) {
            return;
        }

        $expectedDob = Nic::dateOfBirth($payload['nic'])->toDateString();
        $actualDob = $payload['date_of_birth'] instanceof \DateTimeInterface
            ? CarbonImmutable::instance($payload['date_of_birth'])->toDateString()
            : (string) $payload['date_of_birth'];

        if ($actualDob !== $expectedDob) {
            throw new InvalidPersonData('Date of birth does not match the NIC.');
        }

        $expectedGender = Nic::gender($payload['nic']);
        $actualGender = $payload['gender'] instanceof Gender
            ? $payload['gender']
            : Gender::from((string) $payload['gender']);

        if ($actualGender !== $expectedGender) {
            throw new InvalidPersonData('Gender does not match the NIC.');
        }
    }

    private function assertAge(mixed $dateOfBirth): void
    {
        $dob = $dateOfBirth instanceof \DateTimeInterface
            ? CarbonImmutable::instance($dateOfBirth)->startOfDay()
            : CarbonImmutable::parse((string) $dateOfBirth)->startOfDay();

        $today = CarbonImmutable::now(config('app.timezone'))->startOfDay();
        $age = $dob->diffInYears($today);

        if ($age < 18 || $age > 75) {
            throw new InvalidPersonData('Age must be between 18 and 75.');
        }
    }

    /** @param  array<string, mixed>  $data */
    private function syncNested(Person $person, array $data): void
    {
        if (isset($data['contacts']) && is_array($data['contacts'])) {
            foreach ($data['contacts'] as $contact) {
                $type = $contact['type'] instanceof ContactType
                    ? $contact['type']
                    : ContactType::from((string) $contact['type']);
                $isPrimary = (bool) ($contact['is_primary'] ?? false);

                if ($isPrimary) {
                    $person->contacts()
                        ->where('type', $type->value)
                        ->where('is_primary', true)
                        ->update(['is_primary' => false]);
                }

                $person->contacts()->create([
                    'type' => $type,
                    'value' => $contact['value'],
                    'is_primary' => $isPrimary,
                ]);
            }
        }

        if (isset($data['addresses']) && is_array($data['addresses'])) {
            foreach ($data['addresses'] as $address) {
                $type = $address['type'] instanceof AddressType
                    ? $address['type']
                    : AddressType::from((string) $address['type']);

                $person->addresses()->updateOrCreate(
                    ['type' => $type],
                    [
                        'line1' => $address['line1'],
                        'line2' => $address['line2'] ?? null,
                        'city' => $address['city'],
                        'district' => $address['district'] ?? null,
                        'postal_code' => $address['postal_code'] ?? null,
                    ],
                );
            }
        }
    }

    private function childRelation(string $kind): string
    {
        return match ($kind) {
            'emergency' => 'emergencyContacts',
            'dependent' => 'dependents',
            'qualification' => 'qualifications',
            'experience' => 'experience',
            default => throw new \InvalidArgumentException("Unknown person record [{$kind}]."),
        };
    }
}
