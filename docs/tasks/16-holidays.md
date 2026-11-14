# Task 16 — Holidays

**Depends on:** 06. **Read first:** `AGENTS.md`, `docs/GLOSSARY.md` (section 3).

## Goal

Each company has a list of holidays. Timecards mark a day as a holiday from this list, and overtime on a holiday is flagged. The old system held nine holiday rows, all in one month, so holiday handling never worked.

## Files

- Create module `Modules/Calendar`: model `Holiday`; enum `HolidayKind`; `app/Services/HolidayService.php`; exception `InvalidHoliday`; factory; migration; Livewire `HolidaysIndex` + view; `app/Imports/HolidaysImport.php`; routes.
- Modify: `database/seeders/RolesAndPermissionsSeeder.php` — permission `calendar.manage` (roles `super-admin`, `hr-manager`).
- Tests: `Modules/Calendar/tests/Feature/HolidayServiceTest.php`, `HolidaysScreenTest.php`.

## Schema — `holidays` (`Auditable`, `BelongsToCompany`)

| Column | Type | Notes |
|---|---|---|
| `company_id` | FK | |
| `date` | date | unique with `company_id` |
| `name` | string(100) | |
| `kind` | string(12) | `POYA`, `MERCANTILE`, `PUBLIC`, `COMPANY` |
| timestamps | | |

## Interfaces (must match exactly)

```php
namespace Modules\Calendar\Services;

final class HolidayService
{
    public function add(Company $c, \DateTimeInterface|string $date, string $name, HolidayKind $kind): Holiday;
    public function remove(Holiday $holiday): void;
    public function on(int $companyId, \DateTimeInterface|string $date): ?Holiday;
    /** @return array<string, Holiday> keyed by 'Y-m-d' */
    public function between(int $companyId, \DateTimeInterface|string $from, \DateTimeInterface|string $to): array;
    /** Copies every holiday of $year from one company to another, skipping dates that already exist. Returns rows added. */
    public function copyYear(Company $from, Company $to, int $year): int;
}
```

## Rules

- One holiday per company per date → a second is `InvalidHoliday` ("A holiday already exists on this date.").
- A holiday cannot be added, changed or removed on a date inside a **closed** pay period. Until Task 17 exists this check is a method `isDateLocked(int $companyId, $date): bool` on `HolidayService` returning false; Task 17 replaces its body.
- `remove` hard-deletes (a holiday is configuration, not a business record) and is audited.

## Screen — Holidays (`calendar.holidays.index`; view `org.view`, change `calendar.manage`)

- Uses the selected company; requires one to be selected.
- Year selector (default current year). Table ordered by date: date, weekday, name, kind badge. Add / Edit / Remove.
- "Copy from another company" — pick company and year → `copyYear`.
- "Import from Excel" — columns `date, name, kind`; shows a preview with any row errors (bad date, unknown kind, duplicate) before saving; saves only when there are no errors.

## Tests

`HolidayServiceTest.php`

```php
public function test_add_and_find(): void
{
    $c = Company::factory()->create();
    $h = $this->svc->add($c, '2026-12-23', 'Unduvap Full Moon Poya Day', HolidayKind::POYA);
    $this->assertSame($h->id, $this->svc->on($c->id, '2026-12-23')->id);
    $this->assertNull($this->svc->on($c->id, '2026-12-24'));
}

public function test_holiday_is_per_company(): void
{
    $a = Company::factory()->create(); $b = Company::factory()->create();
    $this->svc->add($a, '2026-12-25', 'Christmas Day', HolidayKind::MERCANTILE);
    $this->assertNull($this->svc->on($b->id, '2026-12-25'));
}

public function test_second_holiday_on_the_same_date_is_refused(): void
{
    $c = Company::factory()->create();
    $this->svc->add($c, '2026-12-25', 'Christmas Day', HolidayKind::MERCANTILE);
    $this->expectException(InvalidHoliday::class);
    $this->svc->add($c, '2026-12-25', 'Company day', HolidayKind::COMPANY);
}

public function test_between_is_inclusive_and_keyed_by_date(): void
{
    $c = Company::factory()->create();
    $this->svc->add($c, '2026-12-01', 'A', HolidayKind::COMPANY); $this->svc->add($c, '2026-12-31', 'B', HolidayKind::COMPANY); $this->svc->add($c, '2027-01-01', 'C', HolidayKind::COMPANY);
    $this->assertSame(['2026-12-01', '2026-12-31'], array_keys($this->svc->between($c->id, '2026-12-01', '2026-12-31')));
}

public function test_copy_year_skips_existing_dates(): void
{
    $a = Company::factory()->create(); $b = Company::factory()->create();
    $this->svc->add($a, '2027-01-14', 'Thai Pongal', HolidayKind::PUBLIC); $this->svc->add($a, '2027-05-01', 'May Day', HolidayKind::MERCANTILE); $this->svc->add($a, '2026-12-25', 'Christmas', HolidayKind::MERCANTILE);
    $this->svc->add($b, '2027-05-01', 'May Day', HolidayKind::MERCANTILE);
    $this->assertSame(1, $this->svc->copyYear($a, $b, 2027));
    $this->assertSame(2, Holiday::withoutGlobalScopes()->where('company_id', $b->id)->count());
}
```

`HolidaysScreenTest.php`

```php
public function test_screen_needs_org_view_and_saving_needs_calendar_manage(): void
public function test_list_shows_only_the_selected_companys_holidays_for_the_year(): void
public function test_import_with_a_bad_row_saves_nothing_and_shows_the_row_error(): void
// rows: ['2027-01-14','Thai Pongal','PUBLIC'], ['14/01/2027','x','PUBLIC'] -> assertSee('Row 2'); Holiday count 0
public function test_import_of_valid_rows_saves_all(): void
```

## Done when

All tests pass; suite and Pint clean; by hand, add the remaining 2026 holidays for one company and copy them to the other.

## Do not

- Do not seed holiday dates. HR enters or imports the official list.
- Do not add weekly rest days here — the operation runs seven days a week and expected working days (Task 17) decide what is owed.
