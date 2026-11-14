# Task 21 — OT approval and late waiver

**Depends on:** 19. **Read first:** `AGENTS.md`, `docs/GLOSSARY.md` (section 3), `docs/hrmis_v2/06_phase_2_plan.md` section 2.3.

## Goal

Overtime worked out by the timecard is only **computed OT**. It is paid only after someone approves it, in full or in part: **approved OT**. Separately, HR can **waive** a day's late minutes with a reason.

In the old system, approved OT lived in its own table and drifted from the monthly summary (2,458 hours approved against 1,716 in the October summary). Here the approval sits on the timecard, and the summary reads it from there.

## Files

- In `Modules/Attendance`: `app/Services/OtApprovalService.php`, `app/Services/LateWaiverService.php`; model `TimecardDecisionLog`; exception `InvalidOtDecision`; migration `create_timecard_decision_logs_table`.
- Modify: `RolesAndPermissionsSeeder` — permissions `ot.approve` (`super-admin`, `hr-manager`), `late.waive` (`super-admin`, `hr-manager`).
- Tests: `Modules/Attendance/tests/Feature/OtApprovalServiceTest.php`, `LateWaiverServiceTest.php`.

Screens are in Task 24.

## Schema — `timecard_decision_logs`

Timecards are not audited (they are recalculated often), so decisions get their own append-only log.

`id`, `timecard_id` FK, `employment_id` FK, `work_date` date, `kind` string(12) (`OT_APPROVED`, `OT_CLEARED`, `LATE_WAIVED`, `LATE_RESTORED`), `old_value` int null, `new_value` int null, `reason` string(255) null, `user_id` FK users null, `created_at`. No update or delete.

## Interfaces (must match exactly)

```php
namespace Modules\Attendance\Services;

final class OtApprovalService
{
    /** Computed OT of a timecard in minutes: ot_morning_minutes + ot_evening_minutes. */
    public function computed(Timecard $t): int;

    /** Approve $minutes (null = all computed). */
    public function approve(Timecard $t, ?int $minutes, User $by, ?string $reason = null): Timecard;

    /** Approve all computed OT on each timecard. Returns ['approved' => n, 'skipped' => [timecard_id => reason]]. */
    public function approveMany(array $timecardIds, User $by): array;

    /** Set approved OT to 0 (refused). Reason required. */
    public function refuse(Timecard $t, User $by, string $reason): Timecard;

    /** Back to undecided (null). */
    public function reset(Timecard $t, User $by): Timecard;

    /** Timecards in the period with computed OT > 0 and ot_approved_minutes null. */
    public function undecidedCount(PayPeriod $period): int;
}

final class LateWaiverService
{
    public function waive(Timecard $t, User $by, string $reason): Timecard;
    public function restore(Timecard $t, User $by): Timecard;
    /** late_in_minutes + early_out_minutes, or 0 when waived. */
    public static function chargeable(Timecard $t): int;
}
```

## Rules

**OT**

- Needs `ot.approve`; the timecard must be visible under the company scope.
- Refused with `InvalidOtDecision` when: the period is closed; the timecard has an `exception`; computed OT is 0; `$minutes` is negative, greater than computed, or not a multiple of `ot_block_minutes` (30).
- `approve`: sets `ot_approved_minutes`, `ot_decided_by`, `ot_decided_at`. Approving less than computed needs a `reason`.
- `refuse` = approve 0 with a reason.
- A user may not decide OT on **their own** timecard (the user's `person_id` equals the employment's `person_id`).
- Every decision writes a `timecard_decision_logs` row (`OT_APPROVED` with old and new minutes; `OT_CLEARED` for `reset`).
- When Task 19's engine clears an approval because computed OT changed, it also writes `OT_CLEARED` with `user_id` null and reason "Computed OT changed" — add that to the engine in this task.
- Three states: `null` = undecided (blocks period close), `0` = refused, `> 0` = approved.

**Late waiver**

- Needs `late.waive`; company scope; period open; `reason` at least 5 characters; the timecard must have late minutes (`late_in_minutes + early_out_minutes > 0`) → else `InvalidOtDecision`.
- `waive` sets `late_waived = true` and logs `LATE_WAIVED` (old = minutes, new = 0). `restore` reverses it and logs `LATE_RESTORED`.
- A waiver survives recomputation (the engine never touches `late_waived`).

## Tests

`OtApprovalServiceTest.php` — now `2026-12-10 09:00:00`. Helper `otDay(Employment $e, string $date, string $out = '19:00:00'): Timecard` punches 08:00 and `$out` and computes; `evening()` = `profiled('2026-01-01', profile: ['ot_mode' => 'EVENING'])`; `approver(Employment $e)` = `hr-manager` user for the company.

```php
public function test_approve_all_computed_ot(): void
{
    $e = $this->evening(); $t = $this->otDay($e, '2026-12-01');                    // 120 computed
    $by = $this->approver($e);
    $t = $this->svc->approve($t, null, $by);
    $this->assertSame([120, $by->id], [$t->ot_approved_minutes, $t->ot_decided_by]);
    $this->assertNotNull($t->ot_decided_at);
    $log = TimecardDecisionLog::sole();
    $this->assertSame(['OT_APPROVED', null, 120, $by->id], [$log->kind, $log->old_value, $log->new_value, $log->user_id]);
}

public function test_partial_approval_needs_a_reason_and_a_whole_block(): void
{
    $e = $this->evening(); $t = $this->otDay($e, '2026-12-01'); $by = $this->approver($e);
    try { $this->svc->approve($t, 60, $by); $this->fail('no reason'); } catch (InvalidOtDecision) {}
    try { $this->svc->approve($t, 45, $by, 'Only one hour authorised'); $this->fail('not a block'); } catch (InvalidOtDecision) {}
    try { $this->svc->approve($t, 150, $by, 'x'); $this->fail('above computed'); } catch (InvalidOtDecision) {}
    $this->assertSame(60, $this->svc->approve($t, 60, $by, 'Only one hour authorised')->ot_approved_minutes);
}

public function test_refuse_sets_zero_and_needs_a_reason(): void
{
    $e = $this->evening(); $t = $this->otDay($e, '2026-12-01'); $by = $this->approver($e);
    try { $this->svc->refuse($t, $by, ''); $this->fail(); } catch (InvalidOtDecision) {}
    $this->assertSame(0, $this->svc->refuse($t, $by, 'Not authorised by supervisor')->ot_approved_minutes);
}

public function test_reset_returns_to_undecided(): void
{
    $e = $this->evening(); $t = $this->svc->approve($this->otDay($e, '2026-12-01'), null, $this->approver($e));
    $this->assertNull($this->svc->reset($t, $this->approver($e))->ot_approved_minutes);
    $this->assertSame(['OT_APPROVED', 'OT_CLEARED'], TimecardDecisionLog::orderBy('id')->pluck('kind')->all());
}

public function test_day_without_computed_ot_or_with_an_exception_cannot_be_decided(): void
{
    $e = $this->evening(); $by = $this->approver($e);
    $none = $this->otDay($e, '2026-12-01', '17:10:00');                              // 10 min -> 0 computed
    try { $this->svc->approve($none, null, $by); $this->fail(); } catch (InvalidOtDecision) {}
    $this->punch($e, '2026-12-02 08:00:00'); $missing = app(TimecardService::class)->compute($e, '2026-12-02');
    $this->expectException(InvalidOtDecision::class);
    $this->svc->approve($missing, null, $by);
}

public function test_nobody_decides_their_own_ot(): void
{
    $e = $this->evening(); $t = $this->otDay($e, '2026-12-01');
    $self = User::factory()->forCompanies([$e->company])->withRole('hr-manager')->create(['person_id' => $e->person_id]);
    $this->expectException(InvalidOtDecision::class);
    $this->svc->approve($t, null, $self);
}

public function test_user_without_permission_is_refused(): void                 // hr-officer -> AuthorizationException

public function test_approve_many_reports_what_was_skipped(): void
{
    $e = $this->evening(); $by = $this->approver($e);
    $a = $this->otDay($e, '2026-12-01'); $b = $this->otDay($e, '2026-12-02', '17:10:00'); $c = $this->otDay($e, '2026-12-03', '18:00:00');
    $res = $this->svc->approveMany([$a->id, $b->id, $c->id], $by);
    $this->assertSame(2, $res['approved']);
    $this->assertSame([$b->id], array_keys($res['skipped']));
    $this->assertSame([120, null, 60], [$a->fresh()->ot_approved_minutes, $b->fresh()->ot_approved_minutes, $c->fresh()->ot_approved_minutes]);
}

public function test_engine_clearing_an_approval_is_logged(): void
{
    $e = $this->evening(); $t = $this->svc->approve($this->otDay($e, '2026-12-01'), null, $this->approver($e));
    $this->punch($e, '2026-12-01 20:00:00'); app(TimecardService::class)->compute($e, '2026-12-01');
    $this->assertNull($t->fresh()->ot_approved_minutes);
    $log = TimecardDecisionLog::orderByDesc('id')->first();
    $this->assertSame(['OT_CLEARED', 120, null, null, 'Computed OT changed'], [$log->kind, $log->old_value, $log->new_value, $log->user_id, $log->reason]);
}

public function test_undecided_count(): void
{
    $e = $this->evening(); $by = $this->approver($e);
    $this->otDay($e, '2026-12-01'); $this->svc->approve($this->otDay($e, '2026-12-02'), null, $by); $this->svc->refuse($this->otDay($e, '2026-12-03'), $by, 'Not authorised');
    $this->otDay($e, '2026-12-04', '17:10:00');                                     // no computed OT
    $this->assertSame(1, $this->svc->undecidedCount(app(PayPeriodService::class)->forDate($e->company_id, '2026-12-01')));
}

public function test_closed_period_cannot_be_decided(): void
```

`LateWaiverServiceTest.php`

```php
public function test_waive_and_restore(): void
{
    $e = $this->profiled('2026-01-01', profile: ['late_deduction' => true]);
    $this->punch($e, '2026-12-01 08:36:00'); $this->punch($e, '2026-12-01 17:00:00');
    $t = app(TimecardService::class)->compute($e, '2026-12-01'); $by = $this->approver($e);
    $this->assertSame(31, LateWaiverService::chargeable($t));

    $t = $this->svc->waive($t, $by, 'Company bus broke down');
    $this->assertTrue($t->late_waived);
    $this->assertSame(31, $t->late_in_minutes);                                      // the fact is kept
    $this->assertSame(0, LateWaiverService::chargeable($t));

    $this->assertSame(31, LateWaiverService::chargeable($this->svc->restore($t, $by)));
    $this->assertSame(['LATE_WAIVED', 'LATE_RESTORED'], TimecardDecisionLog::orderBy('id')->pluck('kind')->all());
}

public function test_waiver_survives_recomputation(): void
// waive; add a later out-punch; compute -> late_waived still true
public function test_day_without_late_minutes_cannot_be_waived(): void
public function test_waiver_needs_a_reason_permission_and_an_open_period(): void
```

## Done when

All tests pass, including Tasks 19 and 20; suite and Pint clean.

## Do not

- Do not create a separate approved-OT table.
- Do not approve OT automatically, at any threshold.
- Do not change `late_in_minutes` or `early_out_minutes` when waiving — set the flag.
