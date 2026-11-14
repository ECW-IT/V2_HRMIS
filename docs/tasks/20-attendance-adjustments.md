# Task 20 — Attendance adjustments

**Depends on:** 19, 05. **Read first:** `AGENTS.md`, `docs/GLOSSARY.md` (section 3).

## Goal

When a punch is missing or wrong, HR records an **adjustment** — "add a punch at this time" or "ignore this punch" — with a reason. The raw punch table is never touched; the timecard is calculated from raw punches plus approved adjustments.

The old system let people create, edit and delete rows in the punch table itself (about 4,000 times), so device data and human corrections could no longer be told apart.

## Files

- In `Modules/Attendance`: model `AttendanceAdjustment`; enums `AdjustmentType`, `AdjustmentStatus`; `app/Services/AdjustmentService.php`; `app/Approvals/AttendanceAdjustmentHandler.php`; exception `InvalidAdjustment`; factory; migration.
- Modify: `TimecardService::effectivePunches` (apply adjustments); `database/seeders/ApprovalDefinitionSeeder.php` — add definition `ATTENDANCE_ADJUSTMENT` (one step, `ROLE` `hr-manager`) to its list; `RolesAndPermissionsSeeder` — permissions `attendance.adjust` (`super-admin`, `hr-manager`, `hr-officer`), `attendance.adjust.direct` (`super-admin`, `hr-manager`); provider registers the handler.
- Tests: `Modules/Attendance/tests/Feature/AdjustmentServiceTest.php`.

Screens are in Task 24.

## Schema — `attendance_adjustments` (`Auditable`, `BelongsToCompany`)

| Column | Type | Notes |
|---|---|---|
| `company_id` | FK | |
| `employment_id` | FK | |
| `work_date` | date | |
| `type` | string(10) | `ADD_PUNCH`, `VOID_PUNCH` |
| `punched_at` | datetime null | for `ADD_PUNCH` |
| `punch_id` | FK punches null | for `VOID_PUNCH` |
| `reason` | string(255) | |
| `status` | string(10) | `PENDING`, `APPROVED`, `REJECTED`, `CANCELLED` |
| `approval_request_id` | FK null | |
| `requested_by` | FK users | |
| `decided_by` | FK users null | |
| `decided_at` | datetime null | |

Index (`employment_id`, `work_date`, `status`).

## Interfaces (must match exactly)

```php
namespace Modules\Attendance\Services;

final class AdjustmentService
{
    public function addPunch(Employment $e, \DateTimeInterface|string $punchedAt, string $reason, User $by): AttendanceAdjustment;
    public function voidPunch(Punch $punch, string $reason, User $by): AttendanceAdjustment;
    public function cancel(AttendanceAdjustment $a, User $by): AttendanceAdjustment;
    /** Adjustments for the employment and work date with status APPROVED. */
    public function approvedFor(int $employmentId, string $workDate): \Illuminate\Support\Collection;
    /** Count of PENDING adjustments with work_date inside the period. */
    public function pendingCount(PayPeriod $period): int;
}
```

## Rules

- Needs `attendance.adjust` → else `AuthorizationException`. The employment must be visible to the user under the company scope.
- `reason` required, at least 5 characters → else `InvalidAdjustment`.
- `work_date` = `WorkDate::of($punchedAt)` for an added punch, `WorkDate::of($punch->punched_at)` for a void.
- Refused with `InvalidAdjustment` when:
  - the work date is in a **closed** period ("That period is closed.");
  - the work date is before the hire date, after the exit date, or `punchedAt` is in the future;
  - `ADD_PUNCH`: a raw punch or an approved/pending added punch already exists for the employment at the same minute;
  - `VOID_PUNCH`: the punch is not resolved to an employment, or a pending/approved void already exists for it.
- **Who it applies for:** a user with `attendance.adjust.direct` → status `APPROVED` at once (`decided_by` = the user), then the timecard is recomputed. Anyone else → status `PENDING` and an approval request (`ATTENDANCE_ADJUSTMENT`, subject = the adjustment, payload = type, time, reason). On approval the handler sets `APPROVED`, `decided_by`, `decided_at` and recomputes the timecard; on rejection `REJECTED`. Nothing changes on the timecard while pending.
- `cancel`: the requester, while `PENDING` → `CANCELLED` (and the approval request is cancelled). An `APPROVED` adjustment in an **open** period may be cancelled by a user with `attendance.adjust.direct`; the timecard is recomputed. Never in a closed period.
- Adjustments are never deleted.

**`TimecardService::effectivePunches`** becomes: raw resolved punches in the work-date window, **minus** punches with an approved `VOID_PUNCH`, **plus** the `punched_at` of approved `ADD_PUNCH` adjustments for that work date. Sorted ascending.

## Tests — `AdjustmentServiceTest.php` (uses `HrFixtures`; now = `2026-12-10 09:00:00`)

Helpers: `manager(Employment $e)` / `officer(Employment $e)` = users for the company with roles `hr-manager` / `hr-officer` and `approval.act`; `punch()` and `tc()` as in Task 19.

```php
public function test_manager_adds_a_missing_out_punch_and_the_day_becomes_full(): void
{
    $e = $this->profiled('2026-01-01'); $this->punch($e, '2026-12-01 08:00:00');
    app(TimecardService::class)->compute($e, '2026-12-01');
    $this->assertSame(TimecardException::MISSING_PUNCH, $this->tc($e, '2026-12-01')->exception);

    $a = $this->svc->addPunch($e, '2026-12-01 17:05:00', 'Forgot to punch out', $this->manager($e));

    $this->assertSame(AdjustmentStatus::APPROVED, $a->status);
    $this->assertSame('2026-12-01', $a->work_date->toDateString());
    $t = $this->tc($e, '2026-12-01');
    $this->assertSame([DayType::FULL, null, 2, '17:05:00'], [$t->day_type, $t->exception, $t->punch_count, $t->last_out->format('H:i:s')]);
    $this->assertSame(1, Punch::count());                                   // raw table untouched
}

public function test_officer_request_waits_for_approval(): void
{
    $e = $this->profiled('2026-01-01'); $this->punch($e, '2026-12-01 08:00:00'); app(TimecardService::class)->compute($e, '2026-12-01');

    $a = $this->svc->addPunch($e, '2026-12-01 17:05:00', 'Forgot to punch out', $this->officer($e));
    $this->assertSame(AdjustmentStatus::PENDING, $a->status);
    $this->assertSame(TimecardException::MISSING_PUNCH, $this->tc($e, '2026-12-01')->exception);

    app(ApprovalService::class)->approve($a->approvalRequest, $this->manager($e));
    $this->assertSame(AdjustmentStatus::APPROVED, $a->fresh()->status);
    $this->assertSame(DayType::FULL, $this->tc($e, '2026-12-01')->day_type);
}

public function test_rejected_request_changes_nothing(): void
{
    $e = $this->profiled('2026-01-01'); $this->punch($e, '2026-12-01 08:00:00'); app(TimecardService::class)->compute($e, '2026-12-01');
    $a = $this->svc->addPunch($e, '2026-12-01 17:05:00', 'Forgot to punch out', $this->officer($e));
    app(ApprovalService::class)->reject($a->approvalRequest, $this->manager($e), 'CCTV shows he left at noon');
    $this->assertSame(AdjustmentStatus::REJECTED, $a->fresh()->status);
    $this->assertSame(1, $this->tc($e, '2026-12-01')->punch_count);
}

public function test_voiding_a_wrong_punch_removes_it_from_the_calculation_only(): void
{
    $e = $this->profiled('2026-01-01');
    $this->punch($e, '2026-12-01 08:00:00'); $this->punch($e, '2026-12-01 17:00:00'); $wrong = $this->punch($e, '2026-12-01 23:40:00');
    $this->svc->voidPunch($wrong, 'Punched by security guard by mistake', $this->manager($e));

    $t = $this->tc($e, '2026-12-01');
    $this->assertSame(['17:00:00', 2], [$t->last_out->format('H:i:s'), $t->punch_count]);
    $this->assertSame(3, Punch::count());
    $this->assertNotNull($wrong->fresh());
}

public function test_added_punch_after_midnight_belongs_to_the_previous_work_date(): void
{
    $e = $this->profiled('2026-01-01', profile: ['ot_mode' => 'EVENING']); $this->punch($e, '2026-12-01 08:00:00');
    $a = $this->svc->addPunch($e, '2026-12-02 00:28:00', 'Night job, device was off', $this->manager($e));
    $this->assertSame('2026-12-01', $a->work_date->toDateString());
    $this->assertSame(390, $this->tc($e, '2026-12-01')->ot_evening_minutes);
}

public function test_reason_is_required(): void
{
    $e = $this->profiled('2026-01-01');
    foreach (['', 'ok'] as $bad) {
        try { $this->svc->addPunch($e, '2026-12-01 17:05:00', $bad, $this->manager($e)); $this->fail(); } catch (InvalidAdjustment) { $this->addToAssertionCount(1); }
    }
}

public function test_same_minute_cannot_be_added_twice(): void
{
    $e = $this->profiled('2026-01-01'); $this->punch($e, '2026-12-01 17:05:20');
    try { $this->svc->addPunch($e, '2026-12-01 17:05:00', 'Forgot to punch out', $this->manager($e)); $this->fail(); } catch (InvalidAdjustment) {}
    $this->svc->addPunch($e, '2026-12-01 17:20:00', 'Forgot to punch out', $this->officer($e));          // pending
    $this->expectException(InvalidAdjustment::class);
    $this->svc->addPunch($e, '2026-12-01 17:20:30', 'Forgot to punch out', $this->manager($e));
}

public function test_a_punch_cannot_be_voided_twice(): void
public function test_future_time_and_dates_outside_the_employment_are_refused(): void
// '2026-12-10 09:00:01' (future), a date before hire, a date after exit -> InvalidAdjustment each

public function test_closed_period_cannot_be_adjusted(): void
{
    Carbon::setTestNow('2027-01-05 09:00:00');
    $e = $this->profiled('2026-01-01'); $pp = app(PayPeriodService::class);
    $pp->markClosed($pp->ensure($e->company, 2026, 12), null);
    $this->expectException(InvalidAdjustment::class);
    $this->svc->addPunch($e, '2026-12-01 17:05:00', 'Forgot to punch out', $this->manager($e));
}

public function test_user_without_permission_or_company_access_is_refused(): void
public function test_cancelling_an_approved_adjustment_recomputes_the_day(): void
// manager adds 17:05 -> FULL; manager cancels -> status CANCELLED, timecard back to MISSING_PUNCH
public function test_requester_can_cancel_a_pending_request(): void          // status CANCELLED and approval request CANCELLED

public function test_pending_count_for_a_period(): void
{
    $e = $this->profiled('2026-01-01'); $o = $this->officer($e);
    $this->svc->addPunch($e, '2026-12-01 17:05:00', 'Forgot to punch out', $o);
    $this->svc->addPunch($e, '2026-12-02 17:05:00', 'Forgot to punch out', $this->manager($e));        // approved, not pending
    $this->svc->addPunch($e, '2026-11-30 17:05:00', 'Forgot to punch out', $o);                         // other period
    $this->assertSame(1, $this->svc->pendingCount(app(PayPeriodService::class)->forDate($e->company_id, '2026-12-01')));
}

public function test_adjustments_are_audited(): void
// one CREATED audit row for the adjustment with the user and reason
```

## Done when

All tests pass, including Task 19's; suite and Pint clean.

## Do not

- Do not insert, update or delete rows in `punches`.
- Do not let an adjustment change a timecard directly — only through `effectivePunches` and `compute`.
- Do not allow adjustments in a closed period, by anyone.
