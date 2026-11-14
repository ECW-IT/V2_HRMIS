# Task 17 — Pay periods and expected working days

**Depends on:** 06, 16. **Read first:** `AGENTS.md`, `docs/GLOSSARY.md` (section 4), `docs/hrmis_v2/04_decisions.md` D3.

## Goal

One list of pay periods — a calendar month per company — and one place for the **expected working days** of each grade in each period. The old systems kept pay cycles in three tables and expected days in two that disagreed.

## Files

- In `Modules/Calendar`: models `PayPeriod`, `PeriodExpectedDays`; enum `PeriodStatus`; services `PayPeriodService`, `ExpectedDaysService`; exceptions `PeriodNotFound`, `InvalidPeriodAction`, `InvalidExpectedDays`; factories; migrations (including `add_monthly_late_grace_minutes_to_companies`); Livewire `PayPeriodsIndex`, `ExpectedDaysGrid` + views; console command `EnsurePayPeriods`; routes.
- Modify: `routes/console.php` — schedule `calendar:ensure-periods` daily at 00:10. `Modules/Calendar/app/Services/HolidayService.php` — real `isDateLocked`. `RolesAndPermissionsSeeder` — permissions `period.manage` (`super-admin`, `hr-manager`, `payroll-officer`), `period.close` (`super-admin`, `hr-manager`).
- Tests: `Modules/Calendar/tests/Feature/PayPeriodServiceTest.php`, `ExpectedDaysServiceTest.php`, `PeriodScreensTest.php`.

## Schema

**`pay_periods`** (`Auditable`, `BelongsToCompany`)

| Column | Type | Notes |
|---|---|---|
| `company_id` | FK | |
| `year` | unsigned smallint | |
| `month` | unsigned tinyint | 1–12 |
| `start_date` | date | first day of the month |
| `end_date` | date | last day of the month |
| `status` | string(10) | `OPEN`, `CLOSED` |
| `closed_at` | datetime null | |
| `closed_by` | FK users null | |
| `close_version` | unsigned smallint | default 0; +1 at every close |

Unique (`company_id`, `year`, `month`).

**`period_expected_days`** (`Auditable`)

`pay_period_id` FK cascade, `grade_id` FK, `days` decimal(4,1), `set_by` FK users null. Unique (`pay_period_id`, `grade_id`).

**`companies`** — add `monthly_late_grace_minutes` unsigned smallint default 240.

## Interfaces (must match exactly)

```php
namespace Modules\Calendar\Services;

final class PayPeriodService
{
    /** Creates the period if missing. */
    public function ensure(Company $c, int $year, int $month): PayPeriod;
    /** Ensures periods for every active company from $from's month to $to's month. Returns rows created. */
    public function ensureRange(\DateTimeInterface|string $from, \DateTimeInterface|string $to): int;
    public function forDate(int $companyId, \DateTimeInterface|string $date): PayPeriod;      // throws PeriodNotFound
    public function isDateLocked(int $companyId, \DateTimeInterface|string $date): bool;      // true when that date's period is CLOSED
    /** The period before / after, or null when it does not exist. */
    public function previous(PayPeriod $p): ?PayPeriod;
    public function next(PayPeriod $p): ?PayPeriod;
    /** Low-level status change used by Task 23. Does not run the checklist. */
    public function markClosed(PayPeriod $p, ?User $by): PayPeriod;
    public function markOpen(PayPeriod $p): PayPeriod;
}

final class ExpectedDaysService
{
    public function set(PayPeriod $p, Grade $g, float $days, ?User $by = null): PeriodExpectedDays;
    public function for(PayPeriod $p, int $gradeId): ?float;
    /** Active grades with no value for the period. @return Collection<Grade> */
    public function missing(PayPeriod $p): \Illuminate\Support\Collection;
    /** Copies every grade's value from the previous period where the target has none. Returns rows added. */
    public function copyFromPrevious(PayPeriod $p): int;
}
```

## Rules

**Periods**

- A period is always a whole calendar month: `start_date` = 1st, `end_date` = last day. Nobody types dates.
- `calendar:ensure-periods` makes sure every active company has periods from **last month** to **next month**. Older periods are created on demand by `ensure()` when something needs them.
- `markClosed`: refused with `InvalidPeriodAction` when (a) today is on or before `end_date` ("The period has not ended."), (b) the previous period exists and is `OPEN` ("Close {month name} first."), (c) already `CLOSED`. Sets `status`, `closed_at`, `closed_by`, `close_version + 1`.
- `markOpen`: refused when the **next** period is `CLOSED` ("Reopen {month name} first."), or already `OPEN`. Keeps `close_version`.
- Several periods may be open at once (the current month, and last month while it is being closed), but they close strictly in order.

**Expected working days**

- `days` must be between 0 and the number of days in the month, in steps of 0.5 → else `InvalidExpectedDays`.
- Cannot be set or changed for a `CLOSED` period → `InvalidExpectedDays` ("The period is closed.").
- Only for active grades.

## Screens

| Screen | Route | Permission | Content |
|---|---|---|---|
| Pay periods | `calendar.periods.index` | view `org.view` | Selected company. Table newest first: month, dates, status badge, closed at / by, version, "Expected days: 4 of 4 set" (red when any missing). Row link to the expected-days grid. Closing and reopening are done from the Period close screen (Task 24), not here. |
| Expected working days | `calendar.expected-days.index` | view `org.view`, change `period.manage` | Selected company. Grid: rows = active grades by rank; columns = the last 3 and next 3 periods. Cells editable for open periods, read-only for closed. Buttons: "Copy from previous month" per column. Empty cells in an open period are highlighted. Also on this screen: the company's **monthly late grace (minutes)** — one number, `period.manage`. |

## Tests

`PayPeriodServiceTest.php`

```php
public function test_ensure_creates_a_whole_calendar_month_once(): void
{
    $c = Company::factory()->create();
    $p = $this->svc->ensure($c, 2027, 2);
    $this->assertSame(['2027-02-01', '2027-02-28', PeriodStatus::OPEN, 0], [$p->start_date->toDateString(), $p->end_date->toDateString(), $p->status, $p->close_version]);
    $this->assertSame($p->id, $this->svc->ensure($c, 2027, 2)->id);
    $this->assertSame(1, PayPeriod::withoutGlobalScopes()->count());
}

public function test_leap_february(): void
{
    $this->assertSame('2028-02-29', $this->svc->ensure(Company::factory()->create(), 2028, 2)->end_date->toDateString());
}

public function test_for_date_finds_the_period_and_fails_clearly_when_missing(): void
{
    $c = Company::factory()->create(); $p = $this->svc->ensure($c, 2026, 12);
    $this->assertSame($p->id, $this->svc->forDate($c->id, '2026-12-31')->id);
    $this->expectException(PeriodNotFound::class);
    $this->svc->forDate($c->id, '2027-01-01');
}

public function test_ensure_range_covers_every_active_company(): void
{
    Company::factory()->count(2)->create(); Company::factory()->create(['is_active' => false]);
    $this->assertSame(6, $this->svc->ensureRange('2026-11-15', '2027-01-03'));      // 2 companies x Nov, Dec, Jan
    $this->assertSame(0, $this->svc->ensureRange('2026-11-15', '2027-01-03'));
}

public function test_period_cannot_be_closed_before_it_ends(): void
{
    $this->travelTo('2026-12-31 23:00:00');
    $p = $this->svc->ensure(Company::factory()->create(), 2026, 12);
    $this->expectException(InvalidPeriodAction::class);
    $this->svc->markClosed($p, null);
}

public function test_periods_close_in_order(): void
{
    $this->travelTo('2027-01-05');
    $c = Company::factory()->create(); $nov = $this->svc->ensure($c, 2026, 11); $dec = $this->svc->ensure($c, 2026, 12);

    try { $this->svc->markClosed($dec, null); $this->fail(); } catch (InvalidPeriodAction $e) { $this->assertStringContainsString('November', $e->getMessage()); }

    $nov = $this->svc->markClosed($nov, null);
    $this->assertSame([PeriodStatus::CLOSED, 1], [$nov->status, $nov->close_version]);
    $this->assertNotNull($nov->closed_at);
    $this->assertSame(PeriodStatus::CLOSED, $this->svc->markClosed($dec, null)->status);
}

public function test_periods_reopen_in_reverse_order_and_keep_their_version(): void
{
    $this->travelTo('2027-01-05');
    $c = Company::factory()->create(); $nov = $this->svc->markClosed($this->svc->ensure($c, 2026, 11), null); $dec = $this->svc->markClosed($this->svc->ensure($c, 2026, 12), null);

    try { $this->svc->markOpen($nov); $this->fail(); } catch (InvalidPeriodAction $e) { $this->assertStringContainsString('December', $e->getMessage()); }

    $dec = $this->svc->markOpen($dec);
    $this->assertSame([PeriodStatus::OPEN, 1], [$dec->status, $dec->close_version]);
    $this->assertSame(2, $this->svc->markClosed($dec, null)->close_version);
}

public function test_is_date_locked_follows_the_period_status(): void
{
    $this->travelTo('2027-01-05');
    $c = Company::factory()->create(); $p = $this->svc->ensure($c, 2026, 12);
    $this->assertFalse($this->svc->isDateLocked($c->id, '2026-12-15'));
    $this->svc->markClosed($p, null);
    $this->assertTrue($this->svc->isDateLocked($c->id, '2026-12-15'));
    $this->assertFalse($this->svc->isDateLocked($c->id, '2027-01-02'));       // no period or open period -> not locked
}

public function test_holiday_cannot_be_changed_in_a_closed_period(): void
{
    $this->travelTo('2027-01-05');
    $c = Company::factory()->create(); $this->svc->markClosed($this->svc->ensure($c, 2026, 12), null);
    $this->expectException(InvalidHoliday::class);
    app(HolidayService::class)->add($c, '2026-12-25', 'Christmas Day', HolidayKind::MERCANTILE);
}
```

`ExpectedDaysServiceTest.php`

```php
public function test_set_get_and_missing(): void
{
    $p = app(PayPeriodService::class)->ensure(Company::factory()->create(), 2026, 12);
    $g1 = Grade::factory()->create(); $g2 = Grade::factory()->create(); Grade::factory()->create(['is_active' => false]);
    $this->svc->set($p, $g1, 24.5);
    $this->assertSame(24.5, $this->svc->for($p, $g1->id));
    $this->assertNull($this->svc->for($p, $g2->id));
    $this->assertSame([$g2->id], $this->svc->missing($p)->pluck('id')->all());
}

public function test_setting_again_replaces_the_value(): void
{
    $p = app(PayPeriodService::class)->ensure(Company::factory()->create(), 2026, 12); $g = Grade::factory()->create();
    $this->svc->set($p, $g, 24); $this->svc->set($p, $g, 25);
    $this->assertSame(25.0, $this->svc->for($p, $g->id));
    $this->assertSame(1, PeriodExpectedDays::count());
}

public function test_invalid_values_are_refused(): void
{
    $p = app(PayPeriodService::class)->ensure(Company::factory()->create(), 2027, 2); $g = Grade::factory()->create();
    foreach ([-1, 28.5, 24.3] as $bad) {                                         // February 2027 has 28 days
        try { $this->svc->set($p, $g, $bad); $this->fail((string) $bad); } catch (InvalidExpectedDays) { $this->addToAssertionCount(1); }
    }
    $this->assertSame(28.0, $this->svc->set($p, $g, 28)->days + 0.0);
}

public function test_closed_period_cannot_be_changed(): void
{
    $this->travelTo('2027-01-05');
    $pp = app(PayPeriodService::class); $p = $pp->markClosed($pp->ensure(Company::factory()->create(), 2026, 12), null);
    $this->expectException(InvalidExpectedDays::class);
    $this->svc->set($p, Grade::factory()->create(), 24);
}

public function test_copy_from_previous_fills_only_empty_grades(): void
{
    $c = Company::factory()->create(); $pp = app(PayPeriodService::class);
    $nov = $pp->ensure($c, 2026, 11); $dec = $pp->ensure($c, 2026, 12);
    $g1 = Grade::factory()->create(); $g2 = Grade::factory()->create();
    $this->svc->set($nov, $g1, 24); $this->svc->set($nov, $g2, 25); $this->svc->set($dec, $g2, 22);

    $this->assertSame(1, $this->svc->copyFromPrevious($dec));
    $this->assertSame(24.0, $this->svc->for($dec, $g1->id));
    $this->assertSame(22.0, $this->svc->for($dec, $g2->id));
}
```

`PeriodScreensTest.php`

```php
public function test_grid_needs_period_manage_to_save(): void
public function test_closed_period_cells_are_read_only(): void
public function test_missing_expected_days_are_flagged_on_the_periods_list(): void
public function test_late_grace_can_be_changed_and_is_audited(): void       // set 180 -> Company::monthly_late_grace_minutes 180; one audit row
```

## Done when

All tests pass; suite and Pint clean; by hand, set expected days for this month for every grade and copy them to next month.

## Do not

- Do not derive expected working days from the calendar. They are entered.
- Do not let anyone type period start or end dates.
- Do not run the close checklist here — Task 23.
