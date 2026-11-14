# Task 22 — Leave

**Depends on:** 17, 05, 09 (19 for the timecard link). **Read first:** `AGENTS.md`, `docs/GLOSSARY.md` (section 5), `docs/hrmis_v2/04_decisions.md` D5, `docs/hrmis_v2/06_phase_2_plan.md` section 2.5 and assumptions A1, A2, A10.

Work through the Steps one at a time and pause after each.

## Goal

Leave built so that a new kind of leave is **configuration, not code**: leave types → policies (how many days) → a ledger (every grant and every day taken) → requests with approval. It launches with one type, **Monthly Leave**.

The old system had four overlapping leave designs, a stored balance that held two rows, and a limit that was never enforced.

## Files

- Create module `Modules/Leave`: models `LeaveType`, `LeavePolicy`, `LeaveLedgerEntry`, `LeaveRequest`; enums `LeavePortion`, `LeaveStatus`, `LedgerEntryKind`; services `LeavePolicyService`, `LeaveBalanceService`, `LeaveService`; `app/Approvals/LeaveRequestHandler.php`; `app/Support/LeaveDays.php` (implements the Attendance contract below); exceptions `InvalidLeaveRequest`, `InsufficientLeaveBalance`; factories; migrations; seeder `LeaveSeeder`; Livewire `LeaveTypesIndex`, `LeavePoliciesIndex` + views; routes.
- In `Modules/Attendance`: `app/Contracts/LeaveDayProvider.php` + a null implementation bound by default; migration adding the FK `timecards.leave_type_id → leave_types.id`.
- Modify `Modules/Workflow`: migration `add_employment_id_to_approval_requests`; migration `make_approval_actions_user_id_nullable` (the column is `NOT NULL` today; the "step skipped" row has no user); `ApprovalService` — implement the `MANAGER` approver type (see Rules; the enum case `ApproverType::MANAGER` already exists and `userMatchesStep` returns false for it) and add an optional `?int $employmentId = null` last parameter on `submit`.
- Modify `database/seeders/ApprovalDefinitionSeeder.php` — definition `LEAVE_REQUEST` (step 1 `MANAGER`, step 2 `ROLE` `hr-manager`). That seeder gives every definition one `hr-manager` step today; change it so each definition lists its own steps. "The workflow seeder" below means this file.
- Modify `Modules/Attendance/app/Services/TimecardService.php` — after computing a day, fill `leave_type_id` and `leave_days` from the `LeaveDayProvider`.
- Modify `RolesAndPermissionsSeeder` — permissions `leave.view`, `leave.request`, `leave.manage`; new role `line-manager`.
- Tests: `Modules/Leave/tests/Feature/{LeavePolicyServiceTest, LeaveBalanceServiceTest, LeaveServiceTest, LeaveApprovalTest}.php`; `Modules/Workflow/tests/Feature/ManagerApproverTest.php`.

Operational screens (request, list, calendar) are in Task 24; this task has only the two setup screens.

## Permissions

| Permission | Roles |
|---|---|
| `leave.view` | `super-admin`, `hr-manager`, `hr-officer`, `payroll-officer`, `auditor`, `line-manager` |
| `leave.request` — enter a request for any employee the user can see | `super-admin`, `hr-manager`, `hr-officer` |
| `leave.manage` — setup, record leave as already approved, cancel approved leave | `super-admin`, `hr-manager` |

`line-manager` also gets `approval.act`, `employee.view`, `attendance.punch.view`. It is for heads of department who only approve; self-service comes in Phase 4.

## Schema

**`leave_types`** (`Auditable`)

| Column | Type | Notes |
|---|---|---|
| `code` | string(10) unique | |
| `name` | string(100) | |
| `counts_as_credited` | boolean | true = a day on this leave counts toward credited days (paid leave) |
| `allows_half_day` | boolean | |
| `enforce_balance` | boolean | true = a request beyond the balance is refused |
| `requires_document` | boolean | default false |
| `is_active` | boolean | default true |

**`leave_policies`** (`Auditable`)

`leave_type_id` FK, `company_id` FK null, `grade_id` FK null, `employment_type` string(12) null, `days_per_period` decimal(4,1), `effective_from` date, `effective_to` date null. A null in `company_id`, `grade_id` or `employment_type` means "any".

**`leave_ledger`** — append-only (model blocks update and delete, like `AuditLog`)

`id`, `employment_id` FK, `leave_type_id` FK, `pay_period_id` FK, `kind` string(10) (`GRANT`, `TAKEN`, `RESTORED`, `ADJUSTMENT`), `days` decimal(4,1) signed, `leave_request_id` FK null, `note` string(255) null, `created_by` FK users null, `created_at`. Index (`employment_id`, `leave_type_id`, `pay_period_id`).

**`leave_requests`** (`Auditable`, `BelongsToCompany`)

`company_id` FK, `employment_id` FK, `leave_type_id` FK, `date` date, `portion` string(8) (`FULL`, `MORNING`, `EVENING`), `days` decimal(2,1) (1.0 or 0.5), `reason` string(255) null, `covering_employment_id` FK null, `status` string(10) (`PENDING`, `APPROVED`, `REJECTED`, `CANCELLED`), `approval_request_id` FK null, `requested_by` FK users, `decided_at` datetime null. Index (`employment_id`, `date`); index (`company_id`, `date`, `status`).

**`approval_requests`** — add `employment_id` FK null (whose manager approves).

## Seed — `LeaveSeeder`

| Leave type | code | counts_as_credited | allows_half_day | enforce_balance |
|---|---|---|---|---|
| Monthly Leave | `ML` | **false** | true | **true** |

Policy: `ML`, any company, any grade, any employment type, **8.0** days per period, effective from `2026-01-01`.
Comment in the seeder: `// PLACEHOLDER values — assumptions A1 and A2 in 06_phase_2_plan.md. Confirm with HR.`

## Interfaces (must match exactly)

```php
namespace Modules\Attendance\Contracts;
interface LeaveDayProvider
{
    /** Approved leave for the employment on the date: ['leave_type_id' => int, 'days' => float] or null. Two half days of one type are summed. */
    public function forDay(int $employmentId, string $date): ?array;
}

namespace Modules\Leave\Services;

final class LeavePolicyService
{
    /** The policy that applies to the employment for the leave type on the date, or null. */
    public function resolve(Employment $e, int $leaveTypeId, \DateTimeInterface|string $date): ?LeavePolicy;
}

final class LeaveBalanceService
{
    /** Writes the GRANT for the period if it is not there yet. Returns the entry or null when no policy applies. */
    public function ensureGrant(Employment $e, int $leaveTypeId, PayPeriod $p): ?LeaveLedgerEntry;
    /** Sum of ledger days for the period (after ensureGrant). */
    public function balance(Employment $e, int $leaveTypeId, PayPeriod $p): float;
    /** balance() minus the days of PENDING requests in the period. */
    public function available(Employment $e, int $leaveTypeId, PayPeriod $p): float;
    public function adjust(Employment $e, int $leaveTypeId, PayPeriod $p, float $days, string $note, User $by): LeaveLedgerEntry;
}

final class LeaveService
{
    /** $opts: reason?, covering_employment_id?, pre_approved? (bool, needs leave.manage) */
    public function request(Employment $e, int $leaveTypeId, \DateTimeInterface|string $date, LeavePortion $portion, User $by, array $opts = []): LeaveRequest;
    /** One request per date, all or nothing. $skipDates: dates to leave out. @return Collection<LeaveRequest> */
    public function requestRange(Employment $e, int $leaveTypeId, $from, $to, User $by, array $opts = [], array $skipDates = []): \Illuminate\Support\Collection;
    public function cancel(LeaveRequest $r, User $by): LeaveRequest;
    /** Approved leave days of the employment in the period, grouped: [leave_type_id => days]. */
    public function approvedDays(int $employmentId, PayPeriod $p): array;
    public function pendingCount(PayPeriod $p): int;
}
```

## Rules

**Policy resolution** — among policies of the leave type effective on the date whose non-null fields all match the employment (company, grade of the assignment effective on the date, employment type): pick the one with the **most non-null fields**; ties → the latest `effective_from`. None → null (the employee has no entitlement to that type).

**Ledger and balance**

- The balance is **per pay period** and never carried to the next (Monthly Leave). A different accrual pattern is a later addition; do not build it.
- `ensureGrant`: one `GRANT` of `days_per_period` per (employment, type, period), resolved on the period's start date — or on the hire date when the employee joined during the period. Idempotent.
- `TAKEN` is negative; `RESTORED` positive; `ADJUSTMENT` either sign with a note.
- `adjust` needs `leave.manage` and an open period.

**Request**

Refused with `InvalidLeaveRequest` when:

- the user lacks `leave.request`, or cannot see the employment (→ `AuthorizationException`);
- the leave type is inactive, or `portion` is a half day and the type does not allow it;
- the date is before the hire date, after the exit date, or more than 90 days ahead;
- the date's pay period is **closed**;
- no policy applies ("This employee has no entitlement to Monthly Leave.");
- it overlaps another `PENDING` or `APPROVED` request of the employee on that date: `FULL` overlaps anything; `MORNING` overlaps `MORNING` and `FULL`; `EVENING` overlaps `EVENING` and `FULL`;
- `covering_employment_id` is the employee themself, is not active, or is in another company;
- `requires_document` types are not handled at launch — skip the check, leave a `// TODO Phase 4` at that point only.

Refused with `InsufficientLeaveBalance` when the type has `enforce_balance` and `days` > `available()` for the date's period. The message states the available days.

`days` = 1.0 for `FULL`, 0.5 otherwise.

**Approval**

- Normal path: status `PENDING`; `ApprovalService::submit('LEAVE_REQUEST', company, LeaveRequest::class, id, CREATE, payload, $by, employmentId: $e->id)`.
- `pre_approved` (needs `leave.manage`): status `APPROVED` at once — used when HR enters a paper form the head of department already signed.
- On approval (handler or pre-approved), in one transaction: status `APPROVED`, `decided_at`; ledger `TAKEN` −days in the date's period; recompute the timecard for that date if it exists and is unlocked.
- On rejection: status `REJECTED`. Nothing else.
- Approval is refused with `InvalidLeaveRequest` if, by then, the period has closed or the balance no longer covers it (with `enforce_balance`).

**Cancel**

- `PENDING`: by the requester or a user with `leave.manage` → `CANCELLED`; the approval request is cancelled.
- `APPROVED`: only with `leave.manage`, only in an open period → `CANCELLED`; ledger `RESTORED` +days; timecard recomputed.
- Never in a closed period.

**`MANAGER` approver type (Workflow)**

For a request with `employment_id` set, the approver of a `MANAGER` step is found in this order:

1. the manager on the employee's assignment effective today (`manager_employment_id`);
2. otherwise the head (`head_employment_id`) of the assignment's org unit, then of each parent org unit upward;
3. skip anyone who is the employee themself, is not `ACTIVE`, or has **no user account** (`users.person_id` = that person, `is_active`).

`canAct` for the step = the user is that approver **and** has `approval.act`. The requester still cannot approve their own request. If nobody qualifies, the step is **skipped**: at submit, and again whenever a step is entered, `ApprovalService` advances past a `MANAGER` step with no resolvable approver and records an `approval_actions` row with `user_id` null, decision `APPROVED`, remarks "No manager with a user account — step skipped". A request with `employment_id` null and a `MANAGER` step is skipped the same way.

**Timecard link**

`LeaveDays::forDay` returns the employee's `APPROVED` requests on the date (summed days; the type of the first). `TimecardService::compute` writes `leave_type_id` and `leave_days` from it on every computation (this replaces Task 19's "never changes the Task 22 columns"). Worked time and leave on the same day are both kept; Task 23 decides what is credited.

## Screens (setup only)

| Screen | Route | Permission | Content |
|---|---|---|---|
| Leave types | `leave.types.index` | view `leave.view`, change `leave.manage` | Table and form with the fields above. Under the "Counts as credited" switch, the text: "On: a day on this leave is paid like a worked day. Off: it is an approved day off and is not paid." |
| Leave policies | `leave.policies.index` | view `leave.view`, change `leave.manage` | Filter by leave type. Table: leave type, company, grade, employment type ("Any" for blanks), days per period, from, to. Form to add; an existing policy is ended (set `effective_to`), not edited, once any grant has been made from it. A "Who gets what" panel: pick an employee and a date → shows the policy that applies and why. |

## Steps

- [ ] 1. Types, policies, seed, policy resolution.
- [ ] 2. Ledger and balance.
- [ ] 3. Workflow: `employment_id` and the `MANAGER` approver.
- [ ] 4. Requests, approval handler, cancel.
- [ ] 5. Timecard link.
- [ ] 6. Setup screens.

## Tests

All use `HrFixtures`; now = `2026-12-10 09:00:00`; `setUp` runs `LeaveSeeder` and the workflow seeder. `$ml` = the `ML` type id. Users: `hr(Employment $e)` = `hr-manager`; `officer(Employment $e)` = `hr-officer`; both for the employee's company with `approval.act` where the role has it. `period($e, '2026-12')` = `PayPeriodService::ensure`.

`LeavePolicyServiceTest.php`

```php
public function test_general_policy_applies_to_everyone(): void
{
    $this->assertSame('8.0', $this->svc->resolve($this->hired('2026-01-01'), $this->ml, '2026-12-01')->days_per_period);
}

public function test_most_specific_policy_wins(): void
{
    $e = $this->hired('2026-01-01'); $grade = $e->currentAssignment()->grade_id;
    LeavePolicy::factory()->create(['leave_type_id' => $this->ml, 'grade_id' => $grade, 'days_per_period' => 6, 'effective_from' => '2026-01-01']);
    LeavePolicy::factory()->create(['leave_type_id' => $this->ml, 'grade_id' => $grade, 'company_id' => $e->company_id, 'days_per_period' => 5, 'effective_from' => '2026-01-01']);
    LeavePolicy::factory()->create(['leave_type_id' => $this->ml, 'grade_id' => Grade::factory()->create()->id, 'days_per_period' => 2, 'effective_from' => '2026-01-01']);
    $this->assertSame('5.0', $this->svc->resolve($e, $this->ml, '2026-12-01')->days_per_period);
}

public function test_policy_dates_are_respected(): void
{
    LeavePolicy::query()->update(['effective_to' => '2026-11-30']);
    LeavePolicy::factory()->create(['leave_type_id' => $this->ml, 'days_per_period' => 6, 'effective_from' => '2026-12-01']);
    $e = $this->hired('2026-01-01');
    $this->assertSame('8.0', $this->svc->resolve($e, $this->ml, '2026-11-30')->days_per_period);
    $this->assertSame('6.0', $this->svc->resolve($e, $this->ml, '2026-12-01')->days_per_period);
}

public function test_employment_type_policy(): void
// policy for INTERN with 2 days: an intern resolves to 2, a permanent employee to 8
public function test_no_matching_policy_returns_null(): void         // delete all policies -> null
```

`LeaveBalanceServiceTest.php`

```php
public function test_grant_is_written_once_per_period(): void
{
    $e = $this->hired('2026-01-01'); $p = $this->period($e, '2026-12');
    $this->svc->ensureGrant($e, $this->ml, $p); $this->svc->ensureGrant($e, $this->ml, $p);
    $this->assertSame(1, LeaveLedgerEntry::where('kind', 'GRANT')->count());
    $this->assertSame(8.0, $this->svc->balance($e, $this->ml, $p));
}

public function test_balance_is_per_period_and_not_carried_forward(): void
{
    $e = $this->hired('2026-01-01'); $nov = $this->period($e, '2026-11'); $dec = $this->period($e, '2026-12');
    $this->svc->adjust($e, $this->ml, $nov, -3, 'Test', $this->hr($e));
    $this->assertSame(5.0, $this->svc->balance($e, $this->ml, $nov));
    $this->assertSame(8.0, $this->svc->balance($e, $this->ml, $dec));
}

public function test_available_subtracts_pending_requests(): void
{
    $e = $this->hired('2026-01-01'); $p = $this->period($e, '2026-12');
    app(LeaveService::class)->request($e, $this->ml, '2026-12-15', LeavePortion::FULL, $this->officer($e));
    app(LeaveService::class)->request($e, $this->ml, '2026-12-16', LeavePortion::MORNING, $this->officer($e));
    $this->assertSame(8.0, $this->svc->balance($e, $this->ml, $p));
    $this->assertSame(6.5, $this->svc->available($e, $this->ml, $p));
}

public function test_joiner_during_the_period_gets_the_policy_on_the_hire_date(): void   // hired 2026-12-05 -> grant 8.0
public function test_ledger_rows_cannot_be_changed_or_deleted(): void
public function test_adjust_needs_leave_manage_and_an_open_period(): void
```

`ManagerApproverTest.php` (Workflow)

```php
public function test_assignment_manager_with_a_user_account_can_act(): void
{
    $c = $this->company(); $boss = $this->hired('2026-01-01', $c); $e = $this->hired('2026-01-01', $c);
    app(EmploymentService::class)->changeAssignment($e, '2026-02-01', ['manager_employment_id' => $boss->id], 'Reporting line');
    $bossUser = User::factory()->forCompanies([$c])->withRole('line-manager')->create(['person_id' => $boss->person_id]);
    $other = User::factory()->forCompanies([$c])->withRole('line-manager')->create();

    $req = $this->submitWithManagerStep($e, by: $this->officer($e));
    $this->assertSame(1, $req->current_step);
    $this->assertTrue($this->svc->canAct($req, $bossUser));
    $this->assertFalse($this->svc->canAct($req, $other));
    $this->assertFalse($this->svc->canAct($req, $this->hr($e)));              // HR is step 2, not step 1
}

public function test_org_unit_head_is_used_when_there_is_no_manager(): void
// no manager on the assignment; the parent org unit's head has a user account -> that user can act at step 1
public function test_step_is_skipped_when_nobody_has_a_user_account(): void
{
    $e = $this->hired('2026-01-01');
    $req = $this->submitWithManagerStep($e, by: $this->officer($e));
    $this->assertSame(2, $req->current_step);
    $skip = $req->actions()->sole();
    $this->assertSame([null, 1, 'No manager with a user account — step skipped'], [$skip->user_id, $skip->step, $skip->remarks]);
    $this->assertTrue($this->svc->canAct($req, $this->hr($e)));
}

public function test_manager_who_has_exited_is_not_an_approver(): void
public function test_employee_who_is_their_own_manager_is_skipped(): void
public function test_manager_cannot_approve_a_request_they_submitted(): void
```

`LeaveServiceTest.php`

```php
public function test_request_is_pending_and_takes_nothing_from_the_ledger(): void
{
    $e = $this->hired('2026-01-01');
    $r = $this->svc->request($e, $this->ml, '2026-12-15', LeavePortion::FULL, $this->officer($e), ['reason' => 'Family event']);
    $this->assertSame([LeaveStatus::PENDING, '1.0', '2026-12-15'], [$r->status, $r->days, $r->date->toDateString()]);
    $this->assertSame(0, LeaveLedgerEntry::where('kind', 'TAKEN')->count());
    $this->assertSame('LEAVE_REQUEST', $r->approvalRequest->definition->code);
    $this->assertSame($e->id, $r->approvalRequest->employment_id);
}

public function test_half_day_is_half_a_day(): void
{
    $e = $this->hired('2026-01-01');
    $r = $this->svc->request($e, $this->ml, '2026-12-15', LeavePortion::EVENING, $this->officer($e));
    $this->assertSame('0.5', $r->days);
}

public function test_overlapping_requests_are_refused(): void
{
    $e = $this->hired('2026-01-01'); $o = $this->officer($e);
    $this->svc->request($e, $this->ml, '2026-12-15', LeavePortion::MORNING, $o);
    $this->assertNotNull($this->svc->request($e, $this->ml, '2026-12-15', LeavePortion::EVENING, $o));      // other half is fine
    foreach ([LeavePortion::MORNING, LeavePortion::FULL] as $p) {
        try { $this->svc->request($e, $this->ml, '2026-12-15', $p, $o); $this->fail($p->value); } catch (InvalidLeaveRequest) { $this->addToAssertionCount(1); }
    }
}

public function test_request_beyond_the_balance_is_refused(): void
{
    $e = $this->hired('2026-01-01'); $o = $this->officer($e);
    $this->svc->requestRange($e, $this->ml, '2026-12-11', '2026-12-18', $o);                  // 8 days
    try { $this->svc->request($e, $this->ml, '2026-12-21', LeavePortion::MORNING, $o); $this->fail(); }
    catch (InsufficientLeaveBalance $ex) { $this->assertStringContainsString('0', $ex->getMessage()); }
    $this->assertNotNull($this->svc->request($e, $this->ml, '2027-01-04', LeavePortion::FULL, $o));   // next period has its own balance
}

public function test_balance_is_not_enforced_when_the_type_says_so(): void
{
    LeaveType::whereKey($this->ml)->update(['enforce_balance' => false]);
    $e = $this->hired('2026-01-01');
    $this->assertCount(10, $this->svc->requestRange($e, $this->ml, '2026-12-11', '2026-12-20', $this->officer($e)));
}

public function test_range_is_all_or_nothing(): void
{
    $e = $this->hired('2026-01-01'); $o = $this->officer($e);
    $this->svc->request($e, $this->ml, '2026-12-13', LeavePortion::FULL, $o);
    try { $this->svc->requestRange($e, $this->ml, '2026-12-11', '2026-12-14', $o); $this->fail(); } catch (InvalidLeaveRequest) {}
    $this->assertSame(1, LeaveRequest::count());
}

public function test_range_can_skip_dates(): void
{
    $e = $this->hired('2026-01-01');
    $made = $this->svc->requestRange($e, $this->ml, '2026-12-11', '2026-12-14', $this->officer($e), [], ['2026-12-13']);
    $this->assertSame(['2026-12-11', '2026-12-12', '2026-12-14'], $made->map(fn ($r) => $r->date->toDateString())->all());
}

public function test_invalid_requests(): void
// each raises InvalidLeaveRequest: date before hire; date after exit; date 91 days ahead; half day on a type with allows_half_day=false;
// inactive type; covering person = self; covering person in another company; employee with no policy
public function test_request_in_a_closed_period_is_refused(): void
public function test_user_without_leave_request_or_company_access_is_refused(): void

public function test_pre_approved_needs_leave_manage_and_is_approved_at_once(): void
{
    $e = $this->hired('2026-01-01');
    try { $this->svc->request($e, $this->ml, '2026-12-15', LeavePortion::FULL, $this->officer($e), ['pre_approved' => true]); $this->fail(); }
    catch (\Illuminate\Auth\Access\AuthorizationException) {}

    $r = $this->svc->request($e, $this->ml, '2026-12-15', LeavePortion::FULL, $this->hr($e), ['pre_approved' => true]);
    $this->assertSame(LeaveStatus::APPROVED, $r->status);
    $this->assertSame(7.0, app(LeaveBalanceService::class)->balance($e, $this->ml, $this->period($e, '2026-12')));
    $this->assertNull($r->approval_request_id);
}

public function test_approved_days_are_grouped_by_type(): void
{
    $e = $this->hired('2026-01-01'); $hr = $this->hr($e);
    $this->svc->request($e, $this->ml, '2026-12-01', LeavePortion::FULL, $hr, ['pre_approved' => true]);
    $this->svc->request($e, $this->ml, '2026-12-02', LeavePortion::MORNING, $hr, ['pre_approved' => true]);
    $this->svc->request($e, $this->ml, '2026-12-03', LeavePortion::FULL, $this->officer($e));           // pending: not counted
    $this->assertSame([$this->ml => 1.5], $this->svc->approvedDays($e->id, $this->period($e, '2026-12')));
    $this->assertSame(1, $this->svc->pendingCount($this->period($e, '2026-12')));
}
```

`LeaveApprovalTest.php`

```php
public function test_manager_then_hr_approval_takes_the_days_and_marks_the_timecard(): void
{
    $c = $this->company(); $boss = $this->hired('2026-01-01', $c); $e = $this->profiled('2026-01-01', $c);
    app(EmploymentService::class)->changeAssignment($e, '2026-02-01', ['manager_employment_id' => $boss->id], 'Reporting line');
    $bossUser = User::factory()->forCompanies([$c])->withRole('line-manager')->create(['person_id' => $boss->person_id]);
    app(TimecardService::class)->compute($e, '2026-12-08');

    $r = app(LeaveService::class)->request($e, $this->ml, '2026-12-08', LeavePortion::FULL, $this->officer($e));
    $approvals = app(ApprovalService::class);

    $approvals->approve($r->approvalRequest, $bossUser);
    $this->assertSame(LeaveStatus::PENDING, $r->fresh()->status);

    $approvals->approve($r->approvalRequest->fresh(), $this->hr($e));
    $this->assertSame(LeaveStatus::APPROVED, $r->fresh()->status);
    $this->assertSame(7.0, app(LeaveBalanceService::class)->balance($e, $this->ml, $this->period($e, '2026-12')));
    $t = Timecard::where('employment_id', $e->id)->whereDate('work_date', '2026-12-08')->sole();
    $this->assertSame([$this->ml, '1.00'], [$t->leave_type_id, $t->leave_days]);
}

public function test_rejection_takes_nothing(): void
public function test_approval_fails_if_the_balance_was_used_up_meanwhile(): void
// two pending requests for the last available day (enforce off while requesting, then on) -> approving the second raises InvalidLeaveRequest and stays PENDING

public function test_cancelling_approved_leave_restores_the_balance_and_the_timecard(): void
{
    $e = $this->profiled('2026-01-01'); $hr = $this->hr($e);
    app(TimecardService::class)->compute($e, '2026-12-08');
    $r = app(LeaveService::class)->request($e, $this->ml, '2026-12-08', LeavePortion::FULL, $hr, ['pre_approved' => true]);

    app(LeaveService::class)->cancel($r, $hr);

    $this->assertSame(LeaveStatus::CANCELLED, $r->fresh()->status);
    $this->assertSame(8.0, app(LeaveBalanceService::class)->balance($e, $this->ml, $this->period($e, '2026-12')));
    $this->assertSame(['TAKEN', 'RESTORED'], LeaveLedgerEntry::whereNotNull('leave_request_id')->orderBy('id')->pluck('kind')->all());
    $t = Timecard::where('employment_id', $e->id)->whereDate('work_date', '2026-12-08')->sole();
    $this->assertSame([null, '0.00'], [$t->leave_type_id, $t->leave_days]);
}

public function test_officer_cannot_cancel_approved_leave_but_can_cancel_their_pending_request(): void
public function test_two_half_days_on_one_date_show_as_one_day_on_the_timecard(): void
public function test_timecard_created_after_the_leave_was_approved_carries_the_leave(): void
// approve leave for 2026-12-09 before any timecard exists; then compute -> leave_days '1.00'
public function test_leave_cannot_be_cancelled_in_a_closed_period(): void
```

## Done when

All tests pass, including earlier suites (adjust Task 19's `test_recompute_updates_the_same_row_and_keeps_decisions` so it no longer expects a hand-set `leave_days` to survive — leave columns now come from approved requests); Pint clean.

## Do not

- Do not store a balance. It is always the sum of the ledger.
- Do not build annual accrual, carry-forward or encashment. Later configuration and a later task.
- Do not let leave change `day_value`. Worked time and leave are separate facts; Task 23 combines them.
- Do not delete leave requests or ledger rows.
