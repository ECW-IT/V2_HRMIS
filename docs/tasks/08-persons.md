# Task 08 — Persons

**Depends on:** 03, 07 (module exists). **Read first:** `AGENTS.md`.

## Goal

A person exists **once**, whichever company they work for and however many times they join. The NIC is the identity key, stored in one format so the same person can never be entered twice.

Background: the old system has 56 NICs on more than one row, because every rejoin or company change created a new employee row.

## Files

- In `Modules/CoreHr`: models `Person`, `PersonContact`, `PersonAddress`, `PersonEmergencyContact`, `PersonDependent`, `PersonQualification`, `PersonExperience`; enums `Gender`, `MaritalStatus`, `ContactType`, `AddressType`; `app/Support/Nic.php`; `app/Services/PersonService.php`; exceptions `InvalidNic`, `DuplicatePerson`, `InvalidPersonData`; factories; migrations (including the FK `users.person_id → persons.id`, `nullOnDelete`).
- Tests: `Modules/CoreHr/tests/Unit/NicTest.php`, `Modules/CoreHr/tests/Feature/PersonServiceTest.php`.

No screens in this task (Task 13).

## Schema

All tables have `id`, timestamps; all models use `Auditable`. **`persons` has no `company_id`.**

**`persons`**

| Column | Type | Notes |
|---|---|---|
| `nic` | string(12) null | unique; always the 12-digit form |
| `nic_as_entered` | string(12) null | what the user typed, upper-cased |
| `passport_no` | string(20) null | unique |
| `title` | string(10) null | |
| `first_name` | string(100) | |
| `middle_name` | string(100) null | |
| `last_name` | string(100) | |
| `name_with_initials` | string(150) | |
| `calling_name` | string(50) null | |
| `date_of_birth` | date | |
| `gender` | string(6) | `MALE` / `FEMALE` |
| `marital_status` | string(10) null | `SINGLE`, `MARRIED`, `DIVORCED`, `WIDOWED` |
| `nationality` | string(50) | default `Sri Lankan` |
| `photo_path` | string(255) null | |
| `nic_mismatch` | boolean | default false — set by the import only |

Check constraint: `nic IS NOT NULL OR passport_no IS NOT NULL`.

**`person_contacts`** — `person_id` FK cascade, `type` string(12) (`MOBILE`, `HOME`, `EMAIL`, `WORK_EMAIL`), `value` string(150), `is_primary` boolean default false.
**`person_addresses`** — `person_id` FK cascade, `type` string(10) (`PERMANENT`, `CURRENT`), `line1` string(150), `line2` string(150) null, `city` string(80), `district` string(50) null, `postal_code` string(10) null; unique (`person_id`, `type`).
**`person_emergency_contacts`** — `person_id` FK cascade, `name` string(150), `relationship` string(50), `phone` string(20), `address` string(255) null.
**`person_dependents`** — `person_id` FK cascade, `name` string(150), `relationship` string(50), `date_of_birth` date null.
**`person_qualifications`** — `person_id` FK cascade, `type` string(50), `institute` string(150), `title` string(150), `year` unsigned smallint null.
**`person_experience`** — `person_id` FK cascade, `employer` string(150), `job_title` string(150), `from_date` date, `to_date` date null.

## Interfaces (must match exactly)

```php
namespace Modules\CoreHr\Support;

final class Nic
{
    public static function normalise(string $raw): string;        // throws InvalidNic
    public static function dateOfBirth(string $normalised): \Carbon\CarbonImmutable;
    public static function gender(string $normalised): Gender;
}

namespace Modules\CoreHr\Services;

final class PersonService
{
    /** Keys as the persons columns; 'nic' may be in either format. Optional nested keys: contacts[], addresses[]. */
    public function create(array $data): Person;
    public function update(Person $person, array $data): Person;
    public function findByNic(string $raw): ?Person;               // returns null for an invalid NIC, never throws
}
```

`DuplicatePerson` has a public readonly `int $existingPersonId`.

## NIC rules

**`normalise`** — trim, upper-case, then:

- exactly 12 digits → returned as is;
- exactly 9 digits followed by `V` or `X` → old format `YYDDDSSSC` + letter → return `'19' . YY . DDD . '0' . SSSC` (the last four of the nine digits).
  Example: `853400937V` → `19` `85` `340` `0` `0937` → `198534000937`;
- anything else → `InvalidNic`.

After conversion, the day number (digits 5–7) must be 1–366 or 501–866 → else `InvalidNic`.

**`gender`** — day number > 500 → `FEMALE`, else `MALE`.

**`dateOfBirth`** — year = digits 1–4. Day number = digits 5–7, minus 500 if over 500. The day number is counted on a calendar in which **February always has 29 days**. So build the date by walking month lengths `[31,29,31,30,31,30,31,31,30,31,30,31]`:

| Day number | Date |
|---|---|
| 1 | 1 January |
| 59 | 28 February |
| 60 | 29 February (invalid in a non-leap year → `InvalidNic`) |
| 61 | 1 March |
| 123 | 2 May |
| 366 | 31 December |

> **Verify before relying on this.** Take five real NICs whose birthdays are known and confirm `dateOfBirth` returns them. If it is off by one day after February in non-leap years, stop and report — do not adjust the tests.

## Service rules

- `create`/`update`: when `nic` is given, store `nic = Nic::normalise(...)` and `nic_as_entered`. An invalid NIC → `InvalidNic`.
- Duplicate `nic` (after normalising) → `DuplicatePerson` with the existing id. Duplicate `passport_no` → `DuplicatePerson`.
- Neither NIC nor passport → `InvalidPersonData` ("Enter a NIC or a passport number.").
- When a NIC is present, `date_of_birth` and `gender` must equal the NIC-derived values → else `InvalidPersonData` ("Date of birth does not match the NIC." / "Gender does not match the NIC.").
- Age on today's date must be at least 16 and at most 75 → else `InvalidPersonData`.
- At most one `is_primary` contact per (`person`, `type`): saving a new primary clears the old one.
- `update` may not change `nic` to a value that belongs to someone else (same `DuplicatePerson`).

## Tests

`Modules/CoreHr/tests/Unit/NicTest.php` (plain PHPUnit, no database)

```php
public function test_twelve_digit_nic_is_unchanged(): void
{
    $this->assertSame('198534000937', Nic::normalise('198534000937'));
}

public function test_old_format_converts_to_twelve_digits(): void
{
    $this->assertSame('198534000937', Nic::normalise('853400937V'));
    $this->assertSame('198534000937', Nic::normalise(' 853400937v '));
    $this->assertSame('199062300123', Nic::normalise('906230123X'));
}

public function test_garbage_is_refused(): void
{
    foreach (['', '12345', '85340093V', '8534009371', 'ABCDEFGHIJKL', '853400937VV', '1985340009371', '853400937A'] as $bad) {
        try { Nic::normalise($bad); $this->fail("accepted [$bad]"); } catch (InvalidNic) { $this->addToAssertionCount(1); }
    }
}

public function test_impossible_day_number_is_refused(): void
{
    foreach (['850000937V', '853670937V', '859000937V'] as $bad) {      // day 000, 367, 900
        try { Nic::normalise($bad); $this->fail("accepted [$bad]"); } catch (InvalidNic) { $this->addToAssertionCount(1); }
    }
}

public function test_gender(): void
{
    $this->assertSame(Gender::MALE, Nic::gender('198534000937'));
    $this->assertSame(Gender::FEMALE, Nic::gender('199062300123'));
}

public function test_date_of_birth_before_march(): void
{
    $this->assertSame('1985-01-01', Nic::dateOfBirth('198500100937')->toDateString());
    $this->assertSame('1985-02-28', Nic::dateOfBirth('198505900937')->toDateString());
}

public function test_date_of_birth_uses_the_366_day_calendar(): void
{
    $this->assertSame('1985-03-01', Nic::dateOfBirth('198506100937')->toDateString());   // day 61
    $this->assertSame('1990-05-02', Nic::dateOfBirth('199012300123')->toDateString());   // day 123
    $this->assertSame('1985-12-05', Nic::dateOfBirth('198534000937')->toDateString());   // day 340
    $this->assertSame('1985-12-31', Nic::dateOfBirth('198536600937')->toDateString());   // day 366
}

public function test_date_of_birth_for_female_subtracts_500(): void
{
    $this->assertSame('1990-05-02', Nic::dateOfBirth('199062300123')->toDateString());   // 623 -> 123
}

public function test_leap_day(): void
{
    $this->assertSame('1988-02-29', Nic::dateOfBirth('198806000937')->toDateString());   // 1988 is a leap year
    $this->expectException(InvalidNic::class);
    Nic::dateOfBirth('198506000937');                                                    // 1985 is not
}
```

`Modules/CoreHr/tests/Feature/PersonServiceTest.php` — helper:

```php
private function valid(array $o = []): array
{
    return array_merge([
        'nic' => '853400937V', 'first_name' => 'Nimal', 'last_name' => 'Perera', 'name_with_initials' => 'N. Perera',
        'date_of_birth' => '1985-12-05', 'gender' => 'MALE',
    ], $o);
}
```

```php
public function test_create_stores_normalised_nic_and_what_was_entered(): void
{
    $p = $this->svc->create($this->valid());
    $this->assertSame('198534000937', $p->nic);
    $this->assertSame('853400937V', $p->nic_as_entered);
    $this->assertSame('Sri Lankan', $p->nationality);
}

public function test_same_person_in_old_and_new_nic_format_is_a_duplicate(): void
{
    $first = $this->svc->create($this->valid(['nic' => '853400937V']));
    try { $this->svc->create($this->valid(['nic' => '198534000937'])); $this->fail('duplicate accepted'); }
    catch (DuplicatePerson $e) { $this->assertSame($first->id, $e->existingPersonId); }
    $this->assertSame(1, Person::count());
}

public function test_find_by_nic_accepts_either_format_and_never_throws(): void
{
    $p = $this->svc->create($this->valid());
    $this->assertSame($p->id, $this->svc->findByNic('198534000937')->id);
    $this->assertSame($p->id, $this->svc->findByNic('853400937v')->id);
    $this->assertNull($this->svc->findByNic('rubbish'));
    $this->assertNull($this->svc->findByNic('901230456V'));
}

public function test_date_of_birth_not_matching_nic_is_refused(): void
{
    $this->expectException(InvalidPersonData::class);
    $this->svc->create($this->valid(['date_of_birth' => '1985-12-06']));
}

public function test_gender_not_matching_nic_is_refused(): void
{
    $this->expectException(InvalidPersonData::class);
    $this->svc->create($this->valid(['gender' => 'FEMALE']));
}

public function test_person_needs_nic_or_passport(): void
{
    $this->expectException(InvalidPersonData::class);
    $this->svc->create($this->valid(['nic' => null]));
}

public function test_passport_only_person_is_allowed(): void
{
    $p = $this->svc->create($this->valid(['nic' => null, 'passport_no' => 'N1234567', 'date_of_birth' => '1990-01-15']));
    $this->assertNull($p->nic);
}

public function test_duplicate_passport_is_refused(): void
{
    $this->svc->create($this->valid(['nic' => null, 'passport_no' => 'N1234567']));
    $this->expectException(DuplicatePerson::class);
    $this->svc->create($this->valid(['nic' => null, 'passport_no' => 'N1234567']));
}

public function test_person_younger_than_sixteen_is_refused(): void
{
    $this->travelTo('2026-12-01');
    $this->expectException(InvalidPersonData::class);
    $this->svc->create($this->valid(['nic' => null, 'passport_no' => 'N1', 'date_of_birth' => '2010-12-02']));
}

public function test_person_aged_exactly_sixteen_is_allowed(): void
{
    $this->travelTo('2026-12-01');
    $this->assertNotNull($this->svc->create($this->valid(['nic' => null, 'passport_no' => 'N1', 'date_of_birth' => '2010-12-01'])));
}

public function test_update_cannot_take_another_persons_nic(): void
{
    $this->svc->create($this->valid());
    $other = $this->svc->create($this->valid(['nic' => '199012300123', 'date_of_birth' => '1990-05-02']));
    $this->expectException(DuplicatePerson::class);
    $this->svc->update($other, ['nic' => '853400937V', 'date_of_birth' => '1985-12-05']);
}

public function test_only_one_primary_contact_per_type(): void
{
    $p = $this->svc->create($this->valid(['contacts' => [['type' => 'MOBILE', 'value' => '0771234567', 'is_primary' => true]]]));
    $this->svc->update($p, ['contacts' => [['type' => 'MOBILE', 'value' => '0719876543', 'is_primary' => true]]]);
    $this->assertSame(1, $p->contacts()->where('type', 'MOBILE')->where('is_primary', true)->count());
    $this->assertSame('0719876543', $p->contacts()->where('is_primary', true)->sole()->value);
}

public function test_person_changes_are_audited(): void
{
    $p = $this->svc->create($this->valid());
    $this->svc->update($p, ['calling_name' => 'Nimal']);
    $this->assertSame(1, $p->auditLogs()->where('event', 'UPDATED')->count());
}
```

(`update` with a `contacts` key **adds** the given contacts; it does not delete existing ones.)

## Done when

All tests pass; suite and Pint clean; the NIC birthday rule has been checked against five real NICs and the result reported.

## Do not

- Do not add `company_id` to `persons`.
- Do not build screens.
- Do not relax the NIC ↔ date-of-birth check for new records.
