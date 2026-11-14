# Task 19 — Timecard engine

**Depends on:** 12, 17, 18. **Read first:** `AGENTS.md`, `docs/GLOSSARY.md` (section 3), `docs/hrmis_v2/06_phase_2_plan.md` sections 2.1–2.3 and 5 (A4–A7).

Work through the Steps one at a time and pause after each. **The examples in the Tests section are the specification** — if HR disagrees with one, change the task file first.

## Goal

Turn punches into one **timecard** per employee per work date: day value, late minutes and computed overtime. This is the calculation the old system did on screen every time and never stored.

Two parts:

1. `DayCalculator` — pure arithmetic on times. No database. Every rule lives here.
2. `TimecardService` — gathers the inputs for an employee and date, calls the calculator, stores the result.

## Files

- In `Modules/Attendance`: `app/Timecards/{DayCalculator, DayResult, WorkDate}.php`; enums `DayType`, `TimecardException`; model `Timecard`; `app/Services/TimecardService.php`; exception `TimecardLocked`; job `RecomputeTimecards`; command `ComputeTimecards`; factory; migration; `config/attendance.php`.
- Modify: `PunchIngestService` and `PunchResolver` (dispatch `RecomputeTimecards` for the affected employment/work-date pairs); `TimeProfileService::set` and `ShiftService::update` (recompute open dates affected); `routes/console.php` (schedule `attendance:compute-timecards` daily at 02:00).
- Tests: `Modules/Attendance/tests/Unit/DayCalculatorTest.php`, `WorkDateTest.php`; `Modules/Attendance/tests/Feature/TimecardServiceTest.php`.

No screens (Task 24).

## Config — `Modules/Attendance/config/attendance.php`

```php
return [
    'day_start_hour' => 5,          // a punch before 05:00 belongs to the previous work date
    'ot_block_minutes' => 30,       // overtime is rounded down to this block
    'min_presence_seconds' => 60,   // first and last punch closer than this = one punch
];
```

## Schema — `timecards` (`BelongsToCompany`; **not** `Auditable` — it is derived data)

| Column | Type | Set by |
|---|---|---|
| `company_id` | FK | engine |
| `employment_id` | FK | engine |
| `work_date` | date | engine — unique with `employment_id` |
| `pay_period_id` | FK | engine |
| `shift_id` | FK null | engine |
| `first_in` | datetime null | engine |
| `last_out` | datetime null | engine |
| `punch_count` | unsigned smallint | engine |
| `day_type` | string(6) | engine: `FULL`, `HALF`, `SHORT`, `NONE` |
| `day_value` | decimal(3,2) | engine: 1, 0.5, 0.25, 0 |
| `short_leave` | boolean | engine |
| `late_in_minutes` | unsigned smallint | engine |
| `early_out_minutes` | unsigned smallint | engine |
| `ot_morning_minutes` | unsigned smallint | engine |
| `ot_evening_minutes` | unsigned smallint | engine |
| `is_holiday` | boolean | engine |
| `exception` | string(16) null | engine: `MISSING_PUNCH`, `SHORT_PRESENCE`, `NO_PROFILE` |
| `computed_at` | datetime | engine |
| `late_waived` | boolean default false | Task 21 |
| `ot_approved_minutes` | unsigned smallint null | Task 21 |
| `ot_decided_by` | FK users null | Task 21 |
| `ot_decided_at` | datetime null | Task 21 |
| `leave_type_id` | unsigned bigint null | Task 22 (FK added there) |
| `leave_days` | decimal(3,2) default 0 | Task 22 |

Index (`pay_period_id`, `employment_id`); index (`company_id`, `work_date`); index (`exception`).

The engine writes **only** the columns marked "engine". It never changes the Task 21 and Task 22 columns, with one exception stated under Rules (approval is cleared when computed OT changes).

## Interfaces (must match exactly)

```php
namespace Modules\Attendance\Timecards;

final class WorkDate
{
    /** The work date a punch belongs to: date of (punchedAt minus day_start_hour hours). */
    public static function of(\DateTimeInterface $punchedAt): string;                 // 'Y-m-d'
    /** [start, end) datetimes that belong to a work date: [date 05:00:00, next day 05:00:00). */
    public static function window(string $workDate): array;                           // [CarbonImmutable, CarbonImmutable]
}

final readonly class DayResult
{
    public function __construct(
        public ?\Carbon\CarbonImmutable $firstIn, public ?\Carbon\CarbonImmutable $lastOut, public int $punchCount,
        public DayType $dayType, public float $dayValue, public bool $shortLeave,
        public int $lateInMinutes, public int $earlyOutMinutes,
        public int $otMorningMinutes, public int $otEveningMinutes,
        public ?TimecardException $exception,
    ) {}
}

final class DayCalculator
{
    /**
     * @param array<int, array{start: string, end: string, paid: bool}> $breaks  times as 'H:i' or 'H:i:s'
     * @param array<int, \DateTimeInterface> $punches  any order; duplicates allowed
     */
    public function calculate(string $workDate, Shift $shift, array $breaks, OtMode $otMode, bool $lateDeduction, array $punches): DayResult;
}

namespace Modules\Attendance\Services;

final class TimecardService
{
    /** Punch times that count for the employee on the work date. Task 20 extends this with adjustments. */
    public function effectivePunches(int $employmentId, string $workDate): array;      // CarbonImmutable[], ascending

    /** Computes and stores the timecard. Returns null when no row applies (see Rules). Throws TimecardLocked in a closed period. */
    public function compute(Employment|int $employment, \DateTimeInterface|string $workDate): ?Timecard;

    /** Every non-exited PUNCH-mode employment of the company, each date in range (not after today). Skips closed periods. Returns rows written. */
    public function computeRange(int $companyId, \DateTimeInterface|string $from, \DateTimeInterface|string $to, ?array $employmentIds = null): int;

    public function isLocked(int $companyId, \DateTimeInterface|string $workDate): bool;
}
```

## Rules — `DayCalculator`

Let `S` = the shift. All shift times are placed on `workDate` (`$shift->at($workDate, 'end_time')`). Punches are sorted and de-duplicated; `in` = earliest, `out` = latest. Comparisons are to the second.

**1. Presence**

| Punches | Result |
|---|---|
| none | `NONE`, value 0, no exception (absent) |
| one, or `out − in` < `min_presence_seconds` | `NONE`, value 0, exception `MISSING_PUNCH`; `firstIn` = the punch, `lastOut` = null; everything else 0 |
| two or more | continue |

**2. Day type** — "by the cut-off" means `in < morning_cutoff + 60 seconds` (so 10:00:59 is in time, 10:01:00 is not).

| `in` | `out` | Day type | Value | Short leave |
|---|---|---|---|---|
| by the cut-off | `≥ end_time` | `FULL` | 1 | no |
| by the cut-off | `≥ early_leave_from` and `< end_time` | `FULL` | 1 | **yes** |
| by the cut-off | `≥ half_day_from` and `< early_leave_from` | `HALF` | 0.5 | no |
| by the cut-off | `< half_day_from` | `NONE` | 0 | no — exception `SHORT_PRESENCE` |
| after the cut-off | `≥ early_leave_from` | `HALF` | 0.5 | no |
| after the cut-off | `≥ half_day_from` and `< early_leave_from` | `SHORT` | 0.25 | no |
| after the cut-off | `< half_day_from` | `NONE` | 0 | no — exception `SHORT_PRESENCE` |

**3. Late-in minutes** — only when `lateDeduction` is true **and** `in` is by the cut-off **and** `in ≥ late_from`:
`floor((in − late_from) in seconds / 60) + 1`. So 08:06:00–08:06:59 is 1 minute. Otherwise 0.

**4. Early-out minutes** — only when `lateDeduction` is true **and** the day is a short-leave day:
`ceil((end_time − out) in seconds / 60)`. So leaving at 16:59:30 is 1 minute. Otherwise 0.

**5. Overtime** — block = `ot_block_minutes`.

- Morning: when `otMode` is `MORNING` or `BOTH` and `in < start_time`: gross = whole minutes of `start_time − in`; subtract unpaid-break overlap with `[in, start_time]`; round **down** to a multiple of the block.
- Evening: when `otMode` is `EVENING` or `BOTH` and `out > end_time`: gross = whole minutes of `out − end_time`; subtract unpaid-break overlap with `[end_time, out]`; round down to a multiple of the block.
- A break whose start time is earlier than `start_time` is taken to be on the **next** calendar day (it falls after midnight). Paid breaks are never subtracted. Overlap is counted in whole minutes.
- Overtime is computed whatever the day type, as long as there are two valid punches.

## Rules — `TimecardService`

**`effectivePunches`** — `punches` with `employment_id` = the employment, `resolution = RESOLVED`, and `punched_at` inside `WorkDate::window($workDate)`.

**`compute`** — in this order:

1. Period of the work date's month is ensured (`PayPeriodService::ensure`). If it is `CLOSED` → `TimecardLocked`.
2. No row (return null, and delete an existing unlocked row) when: the work date is before `hire_date`, after `exit_date`, or after today.
3. Time profile effective on the work date:
   - none **and** no effective punches → no row;
   - none **and** punches exist → row with `exception = NO_PROFILE`, `punch_count` set, `first_in`/`last_out` set, everything else 0 / `NONE`;
   - `attendance_mode = NO_PUNCH` → no row (Task 23 credits these employees without timecards).
4. Otherwise call `DayCalculator` with the profile's shift, its breaks, OT mode and late flag; `is_holiday` = a holiday exists for the company on the work date; upsert on (`employment_id`, `work_date`) writing the engine columns and `computed_at`.
5. If the row already existed and `ot_morning_minutes + ot_evening_minutes` changed, set `ot_approved_minutes`, `ot_decided_by`, `ot_decided_at` to null (it must be approved again).

**`computeRange`** — for each date and each employment, calls `compute`; a `TimecardLocked` is skipped silently; other exceptions are logged with the employment and date and do not stop the run. Must not load all punches at once: process one work date at a time.

**Triggers**

- `PunchIngestService::ingest` and `PunchResolver::resolvePending`: collect the distinct (`employment_id`, `WorkDate::of(punched_at)`) pairs of rows inserted or newly resolved, and dispatch **one** `RecomputeTimecards` job with them. The job calls `compute` for each pair, skipping locked ones.
- `TimeProfileService::set`: recompute that employment from `effective_from` to today (open periods only).
- `ShiftService::update`: recompute every open-period timecard that uses the shift.
- `attendance:compute-timecards {--from=} {--to=} {--company=}`: default range = 3 days ago to yesterday, all active companies. Scheduled nightly so absent days get their `NONE` rows and late punches are picked up.

## Steps

- [ ] 1. `WorkDate` + tests.
- [ ] 2. `DayCalculator` presence and day type + tests.
- [ ] 3. Late-in and early-out + tests.
- [ ] 4. Overtime + tests.
- [ ] 5. Migration, model, `TimecardService::compute` + tests.
- [ ] 6. `computeRange`, job, command, triggers + tests.

## Tests

### `WorkDateTest.php` (plain PHPUnit)

```php
public function test_punch_before_five_belongs_to_the_previous_day(): void
{
    $this->assertSame('2026-12-01', WorkDate::of(new \DateTimeImmutable('2026-12-02 00:28:00')));
    $this->assertSame('2026-12-01', WorkDate::of(new \DateTimeImmutable('2026-12-02 04:59:59')));
    $this->assertSame('2026-12-02', WorkDate::of(new \DateTimeImmutable('2026-12-02 05:00:00')));
    $this->assertSame('2026-12-01', WorkDate::of(new \DateTimeImmutable('2026-12-01 08:00:00')));
}

public function test_window(): void
{
    [$from, $to] = WorkDate::window('2026-12-31');
    $this->assertSame(['2026-12-31 05:00:00', '2027-01-01 05:00:00'], [$from->format('Y-m-d H:i:s'), $to->format('Y-m-d H:i:s')]);
}
```

### `DayCalculatorTest.php` (plain PHPUnit; build the `Shift` with `new Shift([...])`, unsaved)

Fixture used by every test unless stated — shift `D0800`: start 08:00, end 17:00, late_from 08:06, morning_cutoff 10:00, half_day_from 12:30, early_leave_from 15:00; breaks `[['start' => '20:00', 'end' => '20:30', 'paid' => false]]`; work date `2026-12-01`.

```php
private function calc(array $times, OtMode $ot = OtMode::NONE, bool $late = false, ?array $breaks = null, array $shift = []): DayResult
{
    // $times: 'H:i' or 'H:i:s' on the work date; prefix '+' for the next calendar day, e.g. '+00:28'
}
```

**Presence**

```php
public function test_no_punches_is_absent_without_exception(): void
{
    $r = $this->calc([]);
    $this->assertSame([DayType::NONE, 0.0, 0, null, null], [$r->dayType, $r->dayValue, $r->punchCount, $r->exception, $r->firstIn]);
}

public function test_single_punch_is_a_missing_punch(): void
{
    $r = $this->calc(['08:02']);
    $this->assertSame([DayType::NONE, 0.0, 1, TimecardException::MISSING_PUNCH], [$r->dayType, $r->dayValue, $r->punchCount, $r->exception]);
    $this->assertSame('08:02:00', $r->firstIn->format('H:i:s'));
    $this->assertNull($r->lastOut);
}

public function test_two_punches_within_a_minute_are_a_missing_punch(): void
{
    $this->assertSame(TimecardException::MISSING_PUNCH, $this->calc(['08:02:00', '08:02:40'])->exception);
    $this->assertNull($this->calc(['08:02:00', '08:03:00', '17:00'])->exception);
}

public function test_duplicates_and_order_do_not_matter_and_middle_punches_are_ignored(): void
{
    $r = $this->calc(['17:10', '12:00', '08:00', '12:45', '08:00']);
    $this->assertSame(['08:00:00', '17:10:00', 4, DayType::FULL], [$r->firstIn->format('H:i:s'), $r->lastOut->format('H:i:s'), $r->punchCount, $r->dayType]);
}
```

**Day type** — one data-provider test `test_day_type(string $in, string $out, DayType $type, float $value, bool $short, ?TimecardException $ex)`:

| # | in | out | type | value | short leave | exception |
|---|---|---|---|---|---|---|
| 1 | 07:55 | 17:05 | FULL | 1.0 | no | — |
| 2 | 10:00:59 | 17:00:00 | FULL | 1.0 | no | — |
| 3 | 10:01:00 | 17:30 | HALF | 0.5 | no | — |
| 4 | 08:00 | 16:59:59 | FULL | 1.0 | **yes** | — |
| 5 | 08:00 | 15:00:00 | FULL | 1.0 | **yes** | — |
| 6 | 08:00 | 14:59:59 | HALF | 0.5 | no | — |
| 7 | 08:00 | 12:30:00 | HALF | 0.5 | no | — |
| 8 | 08:00 | 12:29:59 | NONE | 0.0 | no | SHORT_PRESENCE |
| 9 | 10:30 | 15:00 | HALF | 0.5 | no | — |
| 10 | 10:30 | 14:59 | SHORT | 0.25 | no | — |
| 11 | 10:30 | 12:30 | SHORT | 0.25 | no | — |
| 12 | 10:30 | 12:29 | NONE | 0.0 | no | SHORT_PRESENCE |
| 13 | 13:00 | 17:30 | HALF | 0.5 | no | — |
| 14 | 16:00 | 19:00 | HALF | 0.5 | no | — |
| 15 | 08:00 | +00:28 | FULL | 1.0 | no | — |

**Late-in** — data-provider `test_late_in(string $in, bool $lateDeduction, int $expected)` with out = 17:00:

| in | late deduction | minutes | Note |
|---|---|---|---|
| 08:05:59 | yes | 0 | before late-from |
| 08:06:00 | yes | 1 | first late minute |
| 08:06:59 | yes | 1 | |
| 08:07:00 | yes | 2 | |
| 08:36:00 | yes | 31 | |
| 10:00:30 | yes | 115 | 114 whole minutes + 1 |
| 10:01:00 | yes | 0 | after the cut-off: the day is already a half day (A4) |
| 08:36:00 | no | 0 | employee not measured |

```php
public function test_late_from_comes_from_the_shift(): void
{
    $r = $this->calc(['08:57', '17:40'], late: true, shift: ['start_time' => '08:20', 'end_time' => '17:40', 'late_from' => '08:21', 'morning_cutoff' => '10:20', 'half_day_from' => '12:50', 'early_leave_from' => '15:20']);
    $this->assertSame(37, $r->lateInMinutes);        // matches a real record from the old system
}
```

**Early-out** — data-provider `test_early_out(string $out, bool $lateDeduction, int $expected)` with in = 08:00:

| out | late deduction | minutes | Note |
|---|---|---|---|
| 17:00:00 | yes | 0 | on time |
| 16:59:30 | yes | 1 | each started minute |
| 16:30:00 | yes | 30 | |
| 15:00:00 | yes | 120 | start of the early-leave window |
| 14:59:00 | yes | 0 | half day — not a short-leave day (A5) |
| 16:30:00 | no | 0 | |

**Overtime, evening** — data-provider `test_evening_ot(string $out, OtMode $mode, int $expected)` with in = 08:00:

| out | mode | minutes | Working |
|---|---|---|---|
| 17:29 | EVENING | 0 | 29 → below one block |
| 17:30 | EVENING | 30 | |
| 18:03 | EVENING | 60 | 63 → 60 |
| 19:59 | EVENING | 150 | 179 → 150 |
| 20:15 | EVENING | 180 | 195 − 15 break = 180 |
| 20:30 | EVENING | 180 | 210 − 30 break = 180 |
| 22:28 | EVENING | 270 | 328 − 30 = 298 → 270 |
| +00:28 | EVENING | 390 | 448 − 30 = 418 → 390 |
| 19:00 | BOTH | 120 | |
| 19:00 | MORNING | 0 | not eligible for evening OT |
| 19:00 | NONE | 0 | |

**Overtime, morning** — data-provider `test_morning_ot(string $in, OtMode $mode, int $expected)` with out = 17:00:

| in | mode | minutes | Working |
|---|---|---|---|
| 07:24 | BOTH | 30 | 36 → 30 |
| 07:31 | BOTH | 0 | 29 |
| 07:00 | MORNING | 60 | |
| 06:00 | BOTH | 120 | |
| 07:00 | EVENING | 0 | not eligible for morning OT |

```php
public function test_paid_break_is_not_subtracted(): void
{
    $r = $this->calc(['08:00', '20:30'], OtMode::EVENING, breaks: [['start' => '20:00', 'end' => '20:30', 'paid' => true]]);
    $this->assertSame(210, $r->otEveningMinutes);
}

public function test_two_unpaid_breaks_are_both_subtracted(): void
{
    $r = $this->calc(['08:30', '21:00'], OtMode::EVENING,
        breaks: [['start' => '18:00', 'end' => '18:15', 'paid' => false], ['start' => '20:00', 'end' => '20:30', 'paid' => false]],
        shift: ['start_time' => '08:30', 'end_time' => '17:30', 'late_from' => '08:36', 'morning_cutoff' => '10:30', 'half_day_from' => '12:30', 'early_leave_from' => '15:30']);
    $this->assertSame(150, $r->otEveningMinutes);     // 210 − 15 − 30 = 165 → 150
}

public function test_break_after_midnight_is_on_the_next_day(): void
{
    $r = $this->calc(['08:00', '+01:30'], OtMode::EVENING, breaks: [['start' => '00:30', 'end' => '01:00', 'paid' => false]]);
    $this->assertSame(480, $r->otEveningMinutes);     // 510 − 30 = 480
}

public function test_overtime_is_computed_on_a_half_day_too(): void
{
    $r = $this->calc(['13:00', '19:00'], OtMode::EVENING);
    $this->assertSame([DayType::HALF, 120], [$r->dayType, $r->otEveningMinutes]);
}

public function test_missing_punch_day_has_no_late_and_no_overtime(): void
{
    $r = $this->calc(['08:30'], OtMode::BOTH, late: true);
    $this->assertSame([0, 0, 0, 0], [$r->lateInMinutes, $r->earlyOutMinutes, $r->otMorningMinutes, $r->otEveningMinutes]);
}

public function test_a_full_example(): void
{
    $r = $this->calc(['07:24', '18:03'], OtMode::BOTH, late: true);
    $this->assertSame([DayType::FULL, 1.0, false, 0, 0, 30, 60, null],
        [$r->dayType, $r->dayValue, $r->shortLeave, $r->lateInMinutes, $r->earlyOutMinutes, $r->otMorningMinutes, $r->otEveningMinutes, $r->exception]);
}
```

### `TimecardServiceTest.php` (uses `HrFixtures`)

`setUp`: `Carbon::setTestNow('2026-12-10 09:00:00')`; `Queue::fake()` is **not** used (jobs run synchronously in tests). Helpers: `punch(Employment $e, string $at)` inserts a `RESOLVED` punch for the employment on a factory device; `tc(Employment $e, string $date): ?Timecard` reads the stored row.

```php
public function test_compute_stores_the_calculated_day(): void
{
    $e = $this->profiled('2026-01-01', profile: ['ot_mode' => 'EVENING', 'late_deduction' => true]);
    $this->punch($e, '2026-12-01 08:10:00'); $this->punch($e, '2026-12-01 18:40:00');

    $t = app(TimecardService::class)->compute($e, '2026-12-01');

    $this->assertSame([DayType::FULL, '1.00', 5, 90, 2, null], [$t->day_type, $t->day_value, $t->late_in_minutes, $t->ot_evening_minutes, $t->punch_count, $t->exception]);
    $this->assertSame('2026-12-01 08:10:00', $t->first_in->format('Y-m-d H:i:s'));
    $this->assertSame($e->company_id, $t->company_id);
    $this->assertSame([2026, 12], [$t->payPeriod->year, $t->payPeriod->month]);
    $this->assertNull($t->ot_approved_minutes);
}

public function test_day_without_punches_gets_an_absent_row(): void
{
    $t = app(TimecardService::class)->compute($this->profiled('2026-01-01'), '2026-12-01');
    $this->assertSame([DayType::NONE, '0.00', 0, null], [$t->day_type, $t->day_value, $t->punch_count, $t->exception]);
}

public function test_punch_after_midnight_counts_for_the_previous_work_date(): void
{
    $e = $this->profiled('2026-01-01', profile: ['ot_mode' => 'EVENING']);
    $this->punch($e, '2026-12-01 08:00:00'); $this->punch($e, '2026-12-02 00:28:00');
    $svc = app(TimecardService::class);
    $this->assertSame(390, $svc->compute($e, '2026-12-01')->ot_evening_minutes);
    $this->assertSame(0, $svc->compute($e, '2026-12-02')->punch_count);
}

public function test_unresolved_and_other_employees_punches_are_not_used(): void
{
    $e = $this->profiled('2026-01-01'); $other = $this->profiled('2026-01-01');
    $this->punch($other, '2026-12-01 08:00:00'); $this->punch($other, '2026-12-01 17:00:00');
    Punch::factory()->create(['employment_id' => null, 'resolution' => 'UNRESOLVED', 'punched_at' => '2026-12-01 08:00:00']);
    $this->assertSame(0, app(TimecardService::class)->compute($e, '2026-12-01')->punch_count);
}

public function test_holiday_is_flagged(): void
{
    $e = $this->profiled('2026-01-01');
    app(HolidayService::class)->add($e->company, '2026-12-01', 'Test holiday', HolidayKind::COMPANY);
    $this->assertTrue(app(TimecardService::class)->compute($e, '2026-12-01')->is_holiday);
    $this->assertFalse(app(TimecardService::class)->compute($e, '2026-12-02')->is_holiday);
}

public function test_no_row_outside_the_employment_or_in_the_future(): void
{
    $e = $this->profiled('2026-12-05'); $svc = app(TimecardService::class);
    $this->assertNull($svc->compute($e, '2026-12-04'));         // before hire
    $this->assertNull($svc->compute($e, '2026-12-11'));         // after today (2026-12-10)
    $this->assertNotNull($svc->compute($e, '2026-12-05'));
    app(EmploymentService::class)->exit($e, '2026-12-08', ExitReason::RESIGNATION);
    $this->assertNull($svc->compute($e->fresh(), '2026-12-09'));   // after exit
}

public function test_punches_without_a_time_profile_raise_no_profile(): void
{
    $e = $this->hired('2026-01-01'); $this->punch($e, '2026-12-01 08:00:00'); $this->punch($e, '2026-12-01 17:00:00');
    $t = app(TimecardService::class)->compute($e, '2026-12-01');
    $this->assertSame([TimecardException::NO_PROFILE, DayType::NONE, 2], [$t->exception, $t->day_type, $t->punch_count]);
    $this->assertNull(app(TimecardService::class)->compute($e, '2026-12-02'));     // no profile and no punches -> no row
}

public function test_no_punch_employees_get_no_timecards(): void
{
    $e = $this->profiled('2026-01-01', profile: ['attendance_mode' => 'NO_PUNCH']);
    $this->assertNull(app(TimecardService::class)->compute($e, '2026-12-01'));
}

public function test_the_profile_effective_on_the_work_date_is_used(): void
{
    $e = $this->profiled('2026-01-01', profile: ['ot_mode' => 'NONE']);
    app(TimeProfileService::class)->set($e, ['effective_from' => '2026-12-02', 'shift_id' => app(TimeProfileService::class)->on($e, '2026-12-01')->shift_id, 'ot_mode' => 'EVENING']);
    foreach (['2026-12-01', '2026-12-02'] as $d) { $this->punch($e, "$d 08:00:00"); $this->punch($e, "$d 19:00:00"); }
    $svc = app(TimecardService::class);
    $this->assertSame(0, $svc->compute($e, '2026-12-01')->ot_evening_minutes);
    $this->assertSame(120, $svc->compute($e, '2026-12-02')->ot_evening_minutes);
}

public function test_recompute_updates_the_same_row_and_keeps_decisions(): void
{
    $e = $this->profiled('2026-01-01', profile: ['late_deduction' => true]); $svc = app(TimecardService::class);
    $this->punch($e, '2026-12-01 08:10:00'); $this->punch($e, '2026-12-01 17:00:00');
    $t = $svc->compute($e, '2026-12-01');
    $t->forceFill(['late_waived' => true, 'leave_days' => 0.5])->save();

    $this->punch($e, '2026-12-01 17:20:00');
    $again = $svc->compute($e, '2026-12-01');

    $this->assertSame($t->id, $again->id);
    $this->assertSame('17:20:00', $again->last_out->format('H:i:s'));
    $this->assertTrue($again->late_waived);
    $this->assertSame('0.50', $again->leave_days);
    $this->assertSame(1, Timecard::where('employment_id', $e->id)->count());
}

public function test_ot_approval_is_cleared_when_computed_ot_changes(): void
{
    $e = $this->profiled('2026-01-01', profile: ['ot_mode' => 'EVENING']); $svc = app(TimecardService::class);
    $this->punch($e, '2026-12-01 08:00:00'); $this->punch($e, '2026-12-01 18:00:00');
    $t = $svc->compute($e, '2026-12-01'); $t->forceFill(['ot_approved_minutes' => 60, 'ot_decided_at' => now()])->save();

    $svc->compute($e, '2026-12-01');
    $this->assertSame(60, $this->tc($e, '2026-12-01')->ot_approved_minutes);       // nothing changed -> kept

    $this->punch($e, '2026-12-01 19:00:00');
    $svc->compute($e, '2026-12-01');
    $this->assertSame(120, $this->tc($e, '2026-12-01')->ot_evening_minutes);
    $this->assertNull($this->tc($e, '2026-12-01')->ot_approved_minutes);
    $this->assertNull($this->tc($e, '2026-12-01')->ot_decided_at);
}

public function test_closed_period_is_locked(): void
{
    Carbon::setTestNow('2027-01-05 09:00:00');
    $e = $this->profiled('2026-01-01'); $svc = app(TimecardService::class);
    $before = $svc->compute($e, '2026-12-01');
    $pp = app(PayPeriodService::class); $pp->markClosed($pp->forDate($e->company_id, '2026-12-01'), null);

    $this->assertTrue($svc->isLocked($e->company_id, '2026-12-01'));
    $this->punch($e, '2026-12-01 08:00:00'); $this->punch($e, '2026-12-01 17:00:00');
    try { $svc->compute($e, '2026-12-01'); $this->fail(); } catch (TimecardLocked) {}
    $this->assertSame(0, $before->fresh()->punch_count);
}

public function test_compute_range_fills_every_day_for_every_punch_employee(): void
{
    $c = $this->company(); $a = $this->profiled('2026-01-01', $c); $b = $this->profiled('2026-12-03', $c);
    $this->profiled('2026-01-01', $c, ['attendance_mode' => 'NO_PUNCH']); $this->profiled('2026-01-01', $this->company());

    $written = app(TimecardService::class)->computeRange($c->id, '2026-12-01', '2026-12-05');

    $this->assertSame(8, $written);                                   // a: 5 days, b: 3 days (from the 3rd)
    $this->assertSame(5, Timecard::where('employment_id', $a->id)->count());
    $this->assertSame(3, Timecard::where('employment_id', $b->id)->count());
}

public function test_compute_range_stops_at_today(): void
{
    $c = $this->company(); $this->profiled('2026-01-01', $c);
    $this->assertSame(2, app(TimecardService::class)->computeRange($c->id, '2026-12-09', '2026-12-31'));     // 9th and 10th only
}

public function test_ingesting_punches_recomputes_the_affected_days(): void
{
    $e = $this->profiled('2026-01-01'); app(EnrolmentService::class)->enrol($e, 'NIT', 116, '2026-01-01');
    [$device] = app(DeviceService::class)->register(['serial_no' => 'T1', 'name' => 'T1', 'location_id' => Location::factory()->create()->id, 'device_group' => 'NIT']);

    app(PunchIngestService::class)->ingest($device, [
        ['device_user_id' => 116, 'punched_at' => '2026-12-01 08:00:00'], ['device_user_id' => 116, 'punched_at' => '2026-12-01 17:05:00'],
    ]);

    $this->assertSame(DayType::FULL, $this->tc($e, '2026-12-01')->day_type);
}

public function test_enrolling_later_resolves_punches_and_computes_those_days(): void
{
    $e = $this->profiled('2026-01-01');
    [$device] = app(DeviceService::class)->register(['serial_no' => 'T1', 'name' => 'T1', 'location_id' => Location::factory()->create()->id, 'device_group' => 'NIT']);
    app(PunchIngestService::class)->ingest($device, [['device_user_id' => 999, 'punched_at' => '2026-12-01 08:00:00'], ['device_user_id' => 999, 'punched_at' => '2026-12-01 17:05:00']]);
    $this->assertNull($this->tc($e, '2026-12-01'));

    app(EnrolmentService::class)->enrol($e, 'NIT', 999, '2026-12-01');
    $this->assertSame(DayType::FULL, $this->tc($e, '2026-12-01')->day_type);
}

public function test_changing_a_time_profile_recomputes_from_its_start(): void
{
    $e = $this->profiled('2026-01-01', profile: ['ot_mode' => 'NONE']);
    $this->punch($e, '2026-12-08 08:00:00'); $this->punch($e, '2026-12-08 19:00:00');
    app(TimecardService::class)->compute($e, '2026-12-08');
    app(TimeProfileService::class)->set($e, ['effective_from' => '2026-12-08', 'shift_id' => app(TimeProfileService::class)->on($e, '2026-12-01')->shift_id, 'ot_mode' => 'EVENING']);
    $this->assertSame(120, $this->tc($e, '2026-12-08')->ot_evening_minutes);
}

public function test_one_failing_employee_does_not_stop_the_range(): void
// one employment whose shift row was deleted with DB::table (broken on purpose) + one healthy -> healthy rows written, error logged (Log::spy)
```

## Done when

- All tests pass; suite and Pint clean.
- `php artisan attendance:compute-timecards --from=<first of this month>` on a database with real punches finishes, and ten timecards picked by hand match what the rules give.
- The day-type table and the late and OT examples above have been read by HR and agreed.

## Do not

- Do not put any rule in `TimecardService` that belongs in `DayCalculator`, or the reverse. The calculator never touches the database.
- Do not edit, delete or create punches.
- Do not write to the Task 21 / Task 22 columns, except clearing the OT approval as stated.
- Do not pay a double rate, or treat Sundays differently. Not at launch.
- Do not compute timecards in closed periods.
