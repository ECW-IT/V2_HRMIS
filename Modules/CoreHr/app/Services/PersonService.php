<?php

namespace Modules\CoreHr\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\CoreHr\Enums\AddressType;
use Modules\CoreHr\Enums\ContactType;
use Modules\CoreHr\Enums\Gender;
use Modules\CoreHr\Enums\MaritalStatus;
use Modules\CoreHr\Exceptions\DuplicatePerson;
use Modules\CoreHr\Exceptions\InvalidNic;
use Modules\CoreHr\Exceptions\InvalidPersonData;
use Modules\CoreHr\Models\Person;
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
                'first_name',
                'middle_name',
                'last_name',
                'name_with_initials',
                'calling_name',
                'date_of_birth',
                'gender',
                'marital_status',
                'nationality',
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
            'first_name' => $data['first_name'] ?? $existing->first_name,
            'middle_name' => array_key_exists('middle_name', $data) ? $data['middle_name'] : $existing?->middle_name,
            'last_name' => $data['last_name'] ?? $existing->last_name,
            'name_with_initials' => $data['name_with_initials'] ?? $existing->name_with_initials,
            'calling_name' => array_key_exists('calling_name', $data) ? $data['calling_name'] : $existing?->calling_name,
            'date_of_birth' => $dob,
            'gender' => $gender,
            'marital_status' => $marital,
            'nationality' => $data['nationality'] ?? $existing?->nationality ?? 'Sri Lankan',
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

        if ($age < 16 || $age > 75) {
            throw new InvalidPersonData('Age must be between 16 and 75.');
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
}
