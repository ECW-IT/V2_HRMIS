# Task 18 — Shifts and time profiles

**Depends on:** 09, 16. **Read first:** `AGENTS.md`, `docs/GLOSSARY.md` (section 3), `docs/hrmis_v2/06_phase_2_plan.md` sections 2.1–2.3.

## Goal

A **shift** holds the times that decide a day's value, lateness and overtime. A **time profile** says, for each employee over a date range, which shift they work and which time rules apply to them. In the old system these were spread over "job categories", three other tables and four columns on the employee row.

## Files

- In `Modules/Attendance`: models `Shift`, `ShiftBreak`, `EmploymentTimeProfile`; enums `AttendanceMode`, `OtMode`; services `ShiftService`, `TimeProfileService`; exceptions `InvalidShift`, `InvalidTimeProfile`; factories; migrations; seeder `ShiftSeeder` + `database/seeders/data/shifts.php`; Livewire `ShiftsIndex`, `TimeProfilesBulk`, profile tab component `Tabs/TimeProfile` (added to the employee profile from Task 13); views; routes.
- `Modules/CoreHr/app/Readiness/Checks/Hr012NoTimeProfile.php`; add it to `Modules/CoreHr/config/readiness.php`.
- Modify: `tests/Support/HrFixtures.php` — add `shift(array $o = []): Shift` and `profiled(...)` (see Tests). `RolesAndPermissionsSeeder` — permission `shift.manage` (`super-admin`, `hr-manager`).
- Modify: `Modules/CoreHr/app/Livewire/HireWizard.php` — step 3 gains Shift*, OT mode, "Late deduction applies", attendance mode; saved with `TimeProfileService::set` from the hire date.
- Tests: `Modules/Attendance/tests/Feature/ShiftServiceTest.php`, `TimeProfileServiceTest.php`, `ShiftScreensTest.php`.

## Schema

**`shifts`** (`Auditable`) — shared by both companies.

| Column | Type | Meaning |
|---|---|---|
| `code` | string(10) unique | |
| `name` | string(100) | |
| `start_time` | time | shift start |
| `end_time` | time | shift end |
| `late_from` | time | first minute that counts as late |
| `morning_cutoff` | time | latest arrival that can still earn a full day |
| `half_day_from` | time | leaving from here earns a half day |
| `early_leave_from` | time | leaving from here still earns a full day (short leave) |
| `is_active` | boolean | default true |

**`shift_breaks`** (`Auditable`) — `shift_id` FK cascade, `name` string(50), `start_time` time, `end_time` time, `is_paid` boolean default false.

**`employment_time_profiles`** (`Auditable`, `EffectiveDated` owner `employment_id`) — `employment_id` FK, `effective_from` date, `effective_to` date null, `shift_id` FK, `attendance_mode` string(10), `ot_mode` string(10), `late_deduction` boolean. Unique (`employment_id`, `effective_from`).

`AttendanceMode`: `PUNCH`, `NO_PUNCH`. `OtMode`: `NONE`, `MORNING`, `EVENING`, `BOTH`.

## Seed — `database/seeders/data/shifts.php`

First line: `// From the current system's records (see 06_phase_2_plan.md A11). Confirm the times with HR before go-live.`

| code | name | start | end | late_from | morning_cutoff | half_day_from | early_leave_from | Unpaid breaks |
|---|---|---|---|---|---|---|---|---|
| `D0800` | Day 08:00–17:00 | 08:00 | 17:00 | 08:06 | 10:00 | 12:30 | 15:00 | Dinner 20:00–20:30 |
| `D0830` | Day 08:30–17:30 | 08:30 | 17:30 | 08:36 | 10:30 | 12:30 | 15:30 | Evening snack 18:00–18:15; Dinner 20:00–20:30 |
| `D0730` | Day 07:30–16:30 | 07:30 | 16:30 | 07:36 | 09:30 | 12:00 | 14:30 | Evening snack 18:00–18:15; Dinner 20:00–20:30 |
| `D0700` | Day 07:00–16:00 | 07:00 | 16:00 | 07:06 | 10:00 | 12:30 | 15:00 | (none) |

`ShiftSeeder` uses `updateOrCreate` on `code` and replaces the breaks of each seeded shift.

## Interfaces (must match exactly)

```php
namespace Modules\Attendance\Services;

final class ShiftService
{
    public function create(array $data, array $breaks = []): Shift;      // $breaks: [['name','start_time','end_time','is_paid'], ...]
    public function update(Shift $shift, array $data, ?array $breaks = null): Shift;   // null = leave breaks unchanged
    public function deactivate(Shift $shift): void;
}

final class TimeProfileService
{
    /** $data: effective_from, shift_id, attendance_mode?, ot_mode?, late_deduction? — missing keys copy the profile effective on that date, or default to PUNCH / NONE / false. */
    public function set(Employment $e, array $data): EmploymentTimeProfile;
    public function on(Employment|int $employment, \DateTimeInterface|string $date): ?EmploymentTimeProfile;
    /** Same $data applied to many employments. Returns [employment_id => EmploymentTimeProfile|string error]. */
    public function setMany(array $employmentIds, array $data): array;
}
```

`Shift` model helpers (used by Task 19): `at(string $date, string $field): \Carbon\CarbonImmutable` returns that work date at the field's time (`$shift->at('2026-12-01', 'end_time')` → `2026-12-01 17:00:00`).

## Rules

**Shifts** — all times are on the same day, and must be in this order, else `InvalidShift`:

`start_time < late_from ≤ morning_cutoff < half_day_from < early_leave_from < end_time`

- A break needs `start_time < end_time`. Breaks may lie outside the shift (they matter during overtime).
- Changing the times of a shift affects only timecards in **open** periods (Task 19 recalculates them). If any timecard using the shift lies in a closed period, the change is still allowed — closed timecards are locked and unaffected.
- `deactivate` is refused with `InvalidShift` while any current time profile of a non-exited employment uses the shift.

**Time profiles**

- `effective_from` before the employment's `hire_date` → `InvalidTimeProfile`.
- Employment `EXITED` → `InvalidTimeProfile`.
- Shift must be active.
- `attendance_mode = NO_PUNCH` forces `ot_mode = NONE` and `late_deduction = false`.
- Saved through `EffectiveDating::insert`.
- `effective_from` inside a **closed** pay period of the employment's company → `InvalidTimeProfile` ("That period is closed.").
- `EmploymentService::exit()` closes the open time profile on the exit date (add the call).

**Readiness `HR012`** — BLOCKER, "No time profile": no time profile effective on the as-of date.

## Screens

| Screen | Route | Permission | Content |
|---|---|---|---|
| Shifts | `attendance.shifts.index` | view `org.view`, change `shift.manage` | Table: code, name, start–end, late from, morning cut-off, half day from, early leave from, breaks, employees on it now, active. Form with the six times and a repeatable break list. Under the form, a plain-language preview built from the entered times: "Arrive by 10:00 and leave at 17:00 or later → full day. Leave between 15:00 and 17:00 → full day, short leave. Leave between 12:30 and 15:00 → half day…" |
| Time profile tab | on the employee profile | view `employee.view`, change `employee.update` | Current profile and history (from, to, shift, attendance mode, OT mode, late deduction). "Change" dialog: effective from, shift, attendance mode, OT mode, late deduction. |
| Bulk time profiles | `attendance.time-profiles.bulk` | `employee.update` | Filter employees (org unit, grade, current shift); tick rows; choose effective from + the fields to change; apply with `setMany`; show a result line per employee. |

## Tests

`HrFixtures` additions:

```php
/** Factory shift D0800 values unless overridden: 08:00 / 17:00 / 08:06 / 10:00 / 12:30 / 15:00, with an unpaid Dinner break 20:00–20:30. */
protected function shift(array $o = []): Shift;
/** hired() + time profile from the hire date. $profile keys override: shift_id, attendance_mode, ot_mode, late_deduction (defaults: new shift(), PUNCH, NONE, false). */
protected function profiled(string $hireDate = '2026-01-01', ?Company $c = null, array $profile = [], array $emp = []): Employment;
```

`ShiftServiceTest.php`

```php
public function test_times_must_be_in_order(): void
{
    $ok = ['code' => 'X', 'name' => 'X', 'start_time' => '08:00', 'end_time' => '17:00', 'late_from' => '08:06', 'morning_cutoff' => '10:00', 'half_day_from' => '12:30', 'early_leave_from' => '15:00'];
    $this->assertNotNull($this->svc->create($ok));
    foreach ([['late_from' => '07:59'], ['morning_cutoff' => '08:05'], ['half_day_from' => '10:00'], ['early_leave_from' => '12:30'], ['end_time' => '15:00']] as $i => $bad) {
        try { $this->svc->create(array_merge($ok, ['code' => "B$i"], $bad)); $this->fail(json_encode($bad)); } catch (InvalidShift) { $this->addToAssertionCount(1); }
    }
}

public function test_break_end_must_be_after_start(): void
{
    $this->expectException(InvalidShift::class);
    $this->svc->create($this->shiftData(), [['name' => 'Dinner', 'start_time' => '20:30', 'end_time' => '20:00', 'is_paid' => false]]);
}

public function test_shift_in_use_cannot_be_deactivated(): void
{
    $e = $this->profiled();
    $this->expectException(InvalidShift::class);
    $this->svc->deactivate(app(TimeProfileService::class)->on($e, '2026-06-01')->shift);
}

public function test_seeder_creates_four_shifts_with_breaks_and_can_rerun(): void
{
    $this->seed(ShiftSeeder::class); $this->seed(ShiftSeeder::class);
    $this->assertSame(['D0700', 'D0730', 'D0800', 'D0830'], Shift::orderBy('code')->pluck('code')->all());
    $this->assertSame(2, Shift::where('code', 'D0830')->sole()->breaks()->count());
    $this->assertSame('08:36:00', Shift::where('code', 'D0830')->value('late_from'));
}

public function test_at_builds_a_datetime_on_the_work_date(): void
{
    $this->assertSame('2026-12-01 17:00:00', $this->shift()->at('2026-12-01', 'end_time')->format('Y-m-d H:i:s'));
}
```

`TimeProfileServiceTest.php`

```php
public function test_set_and_read_by_date(): void
{
    $e = $this->hired('2026-01-01'); $s1 = $this->shift(); $s2 = $this->shift(['code' => 'S2']);
    $this->svc->set($e, ['effective_from' => '2026-01-01', 'shift_id' => $s1->id, 'ot_mode' => 'EVENING', 'late_deduction' => true]);
    $this->svc->set($e, ['effective_from' => '2026-07-01', 'shift_id' => $s2->id]);

    $june = $this->svc->on($e, '2026-06-30'); $july = $this->svc->on($e, '2026-07-01');
    $this->assertSame([$s1->id, OtMode::EVENING, true], [$june->shift_id, $june->ot_mode, $june->late_deduction]);
    $this->assertSame([$s2->id, OtMode::EVENING, true], [$july->shift_id, $july->ot_mode, $july->late_deduction]);   // unspecified fields copied
    $this->assertSame('2026-06-30', $june->effective_to->toDateString());
    $this->assertNull($this->svc->on($e, '2025-12-31'));
}

public function test_defaults_for_a_first_profile(): void
{
    $p = $this->svc->set($this->hired('2026-01-01'), ['effective_from' => '2026-01-01', 'shift_id' => $this->shift()->id]);
    $this->assertSame([AttendanceMode::PUNCH, OtMode::NONE, false], [$p->attendance_mode, $p->ot_mode, $p->late_deduction]);
}

public function test_no_punch_mode_switches_off_ot_and_late(): void
{
    $p = $this->svc->set($this->hired('2026-01-01'), ['effective_from' => '2026-01-01', 'shift_id' => $this->shift()->id, 'attendance_mode' => 'NO_PUNCH', 'ot_mode' => 'BOTH', 'late_deduction' => true]);
    $this->assertSame([OtMode::NONE, false], [$p->ot_mode, $p->late_deduction]);
}

public function test_profile_before_hire_date_is_refused(): void
{
    $this->expectException(InvalidTimeProfile::class);
    $this->svc->set($this->hired('2026-06-01'), ['effective_from' => '2026-05-31', 'shift_id' => $this->shift()->id]);
}

public function test_profile_in_a_closed_period_is_refused(): void
{
    $this->travelTo('2027-01-05');
    $e = $this->hired('2026-01-01'); $pp = app(PayPeriodService::class);
    $pp->markClosed($pp->ensure($e->company, 2026, 12), null);
    $this->expectException(InvalidTimeProfile::class);
    $this->svc->set($e, ['effective_from' => '2026-12-15', 'shift_id' => $this->shift()->id]);
}

public function test_inactive_shift_is_refused(): void
public function test_exit_closes_the_time_profile(): void                     // exit 2026-10-31 -> open profile effective_to 2026-10-31
public function test_set_many_reports_each_result(): void
// two active employments + one exited: result has two EmploymentTimeProfile and one error string containing 'exited'

public function test_hr012_reports_employees_without_a_profile(): void
{
    $c = $this->company(); $with = $this->profiled('2026-01-01', $c); $without = $this->hired('2026-01-01', $c);
    $codes = fn (Employment $e) => app(ReadinessService::class)->forEmployment($e, '2026-11-01')->pluck('code')->all();
    $this->assertNotContains('HR012', $codes($with));
    $this->assertContains('HR012', $codes($without));
}
```

`ShiftScreensTest.php`

```php
public function test_shifts_screen_needs_org_view_and_saving_needs_shift_manage(): void
public function test_preview_text_reflects_the_entered_times(): void         // set early_leave_from 15:30 -> assertSee('15:30')
public function test_hire_wizard_saves_a_time_profile_from_the_hire_date(): void
public function test_bulk_screen_changes_the_shift_for_the_ticked_employees_only(): void
```

## Done when

All tests pass, including the earlier suites (update `ReadinessChecksTest::ready()` so a "ready" employee also has a time profile, and the summary test's expected code list to end with `HR012`); Pint clean.

## Do not

- Do not put shift, OT mode or late flags on `employments` or `assignments`.
- Do not create rotating or night shifts. Not in use; add when needed.
- Do not calculate anything from punches here — Task 19.
