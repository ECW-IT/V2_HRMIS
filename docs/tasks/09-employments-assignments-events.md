# Task 09 — Employments, assignments and lifecycle events

**Depends on:** 04, 05, 06, 07, 08. **Read first:** `AGENTS.md`; `docs/hrmis_v2/04_decisions.md` sections D7/D13, D6, D12.

This is the centre of the system. Work through the Steps one at a time and pause after each.

## Goal

An **employment** is one period of work by one person in one company. An **assignment** is where they sit (department, job, grade, location, manager) over a date range. Every lifecycle change is an **event**. Moving to the sister company ends one employment and starts another for the same person, with a new employee number and EPF number.

## Files

- In `Modules/CoreHr`: models `Employment`, `Assignment`, `EmploymentEvent`; enums `EmploymentType`, `EmploymentStatus`, `EventType`, `ExitReason`; `app/Services/EmploymentService.php`, `app/Services/EmploymentRequestService.php`; `app/Approvals/EmployeeChangeHandler.php`, `EmployeeExitHandler.php`; exceptions `ActiveEmploymentExists`, `OutsideEmploymentPeriod`, `InvalidEmploymentData`, `EmploymentNotActive`; factories; migrations.
- Modify: `Modules/Organisation/app/Services/OrgUnitService.php` (`hasCurrentAssignments` real body); migration adding FK `org_units.head_employment_id → employments.id` (`nullOnDelete`); `database/seeders/RolesAndPermissionsSeeder.php` (new permission).
- Modify: `Modules/CoreHr/app/Providers/CoreHrServiceProvider.php` (register the two approval handlers).
- Create: `tests/Support/HrFixtures.php`.
- Tests: `Modules/CoreHr/tests/Feature/EmploymentServiceTest.php`, `SisterCompanyTransferTest.php`, `EmploymentRequestServiceTest.php`.

No screens (Task 13).

## Enums

- `EmploymentType`: `PERMANENT`, `PROBATION`, `INTERN`, `CONTRACT`
- `EmploymentStatus`: `ACTIVE`, `SUSPENDED`, `EXITED`
- `EventType`: `HIRE`, `REJOIN`, `CONFIRMATION`, `ASSIGNMENT_CHANGE`, `TYPE_CHANGE`, `SUSPENSION`, `REINSTATEMENT`, `EXIT`, `SISTER_COMPANY_TRANSFER_OUT`, `SISTER_COMPANY_TRANSFER_IN`
- `ExitReason`: `RESIGNATION`, `TERMINATION`, `RETIREMENT`, `CONTRACT_END`, `SISTER_COMPANY_TRANSFER`, `DECEASED`, `ABANDONMENT`

## Schema

All have `id`, timestamps; all models use `Auditable`.

**`employments`** (uses `BelongsToCompany`)

| Column | Type | Notes |
|---|---|---|
| `person_id` | FK persons | |
| `company_id` | FK companies | |
| `employee_no` | string(20) | unique with `company_id` |
| `legacy_employee_no` | string(50) null | |
| `type` | string(12) | |
| `status` | string(10) | default `ACTIVE` |
| `hire_date` | date | |
| `confirmation_due_date` | date null | |
| `confirmed_on` | date null | |
| `epf_liable` | boolean | |
| `epf_no` | string(20) null | unique with `company_id` |
| `exit_date` | date null | |
| `exit_reason` | string(25) null | |
| `exit_remarks` | text null | |
| `previous_employment_id` | FK employments null | |
| `active_person_id` | unsigned bigint null, **stored generated**: `IF(status <> 'EXITED', person_id, NULL)` | **unique** — one non-exited employment per person |

Check constraints (add with raw `ALTER TABLE … ADD CONSTRAINT … CHECK`): `exit_date IS NULL OR exit_date >= hire_date`; `epf_liable = 0 OR epf_no IS NOT NULL`; `status <> 'EXITED' OR exit_date IS NOT NULL`.

**`assignments`** (uses `EffectiveDated`, `$effectiveOwnerKey = 'employment_id'`)

`employment_id` FK, `effective_from` date, `effective_to` date null, `org_unit_id` FK, `job_id` FK `hr_jobs`, `grade_id` FK, `location_id` FK, `manager_employment_id` FK employments null, `change_reason` string(255) null. Unique (`employment_id`, `effective_from`).

**`employment_events`**

`employment_id` FK, `type` string(30), `event_date` date, `details` json null, `related_employment_id` FK employments null, `approval_request_id` FK approval_requests null, `created_by` FK users null. Index (`employment_id`, `event_date`).

## Interfaces (must match exactly)

```php
namespace Modules\CoreHr\Services;

final class EmploymentService
{
    /** $employment keys: type, hire_date, epf_liable?, epf_no?, legacy_employee_no?, employee_no? (import only).
     *  $assignment keys: org_unit_id, job_id, grade_id, location_id, manager_employment_id? */
    public function hire(Person $person, Company $company, array $employment, array $assignment): Employment;
    public function rejoin(Person $person, Company $company, array $employment, array $assignment): Employment;

    public function confirm(Employment $e, \DateTimeInterface|string $on): Employment;
    public function changeAssignment(Employment $e, \DateTimeInterface|string $from, array $changes, string $reason): Assignment;
    public function suspend(Employment $e, \DateTimeInterface|string $on, string $remarks): Employment;
    public function reinstate(Employment $e, \DateTimeInterface|string $on): Employment;
    public function exit(Employment $e, \DateTimeInterface|string $on, ExitReason $reason, ?string $remarks = null): Employment;

    /** $newEmployment keys: epf_no? (type and epf_liable are copied from $from). */
    public function transferToSisterCompany(Employment $from, Company $to, \DateTimeInterface|string $lastDay,
                                            array $assignment, array $newEmployment = []): Employment;
}

/** Decides between applying directly and submitting for approval. */
final class EmploymentRequestService
{
    /** $operation: 'CONFIRM' | 'CHANGE_ASSIGNMENT' | 'UPDATE_PERSON' | 'SUSPEND' | 'REINSTATE' | 'EXIT' | 'SISTER_TRANSFER'.
     *  Returns the ApprovalRequest when submitted, or null when applied directly. */
    public function request(User $by, string $operation, Employment $e, array $data): ?\Modules\Workflow\Models\ApprovalRequest;
}
```

`Employment` model:

```php
person(): BelongsTo;  company(): BelongsTo;  assignments(): HasMany;  events(): HasMany;  previous(): BelongsTo;
currentAssignment(): ?Assignment;                       // effective today
assignmentOn(\DateTimeInterface|string $date): ?Assignment;
serviceStartDate(): \Carbon\CarbonImmutable;            // this employment's hire_date
isActive(): bool;                                       // status === ACTIVE
```

`tests/Support/HrFixtures.php` (trait used by this and later tasks):

```php
protected function company(array $attrs = []): Company;                 // factory create
protected function person(array $attrs = []): Person;                   // via PersonService, unique valid NIC each call
/** Creates org unit (in $c), job, grade, location; returns ['org_unit_id'=>, 'job_id'=>, 'grade_id'=>, 'location_id'=>] merged with $overrides. */
protected function asg(Company $c, array $overrides = []): array;
/** ['type' => 'PERMANENT', 'hire_date' => '2026-01-01', 'epf_liable' => true, 'epf_no' => <unique>] merged with $overrides. */
protected function emp(array $overrides = []): array;
/** Hires a new person into $c (default: a new company). */
protected function hired(string $hireDate = '2026-01-01', ?Company $c = null, array $emp = [], ?Person $p = null): Employment;
```

To produce unique valid NICs in `person()`: use day number 100 and a per-test counter for the serial — `sprintf('1988100%05d', $n)` gives a male born 1988-04-09 (day 100 on the 366-day calendar); set `date_of_birth` to `1988-04-09` and gender `MALE`.

## Rules

**hire**

1. `hire_date` required; not more than 60 days in the future → else `InvalidEmploymentData`.
2. The person has a non-`EXITED` employment (any company) → `ActiveEmploymentExists`.
3. `type = INTERN` → `epf_liable` forced to false and `epf_no` to null, whatever was passed. Other types → `epf_liable` defaults to true.
4. `epf_liable` true and no `epf_no` → `InvalidEmploymentData` ("EPF number is required.").
5. `epf_no` already used in the company → `InvalidEmploymentData` ("EPF number is already in use in this company.").
6. `type = PROBATION` → `confirmation_due_date = hire_date + 6 months` unless given.
7. `org_unit_id` must belong to the company and be active; job, grade, location must be active → else `InvalidEmploymentData`.
8. `manager_employment_id`, when given, must be an `ACTIVE` employment in the **same company** and not the employee themself.
9. `employee_no`: when not passed, `EmployeeNumberGenerator::next($company, fn ($n) => exists in employments for that company)`. When passed (import), it is used as is.
10. Creates the employment, the first assignment through `EffectiveDating::insert` with `effective_from = hire_date`, and a `HIRE` event dated `hire_date`. One transaction.

**rejoin** — same as `hire`, plus: `previous_employment_id` = the person's most recent `EXITED` employment **in that company**; event type `REJOIN`. No previous exited employment in that company → `InvalidEmploymentData`.

**confirm** — only `ACTIVE` + `PROBATION` → else `EmploymentNotActive` / `InvalidEmploymentData`. `$on` ≥ `hire_date`. Sets `type = PERMANENT`, `confirmed_on = $on`. Event `CONFIRMATION`.

**changeAssignment** — employment must not be `EXITED`. `$from` < `hire_date` → `OutsideEmploymentPeriod`. Copies the assignment effective on `$from`, applies `$changes` (only the five assignment keys), validates as hire rules 7–8, `change_reason = $reason`, `EffectiveDating::insert`. No actual change → `InvalidEmploymentData` ("Nothing was changed."). Event `ASSIGNMENT_CHANGE` with `details = ['from' => [...changed old values], 'to' => [...changed new values]]`.

**suspend / reinstate** — `ACTIVE → SUSPENDED` and back; wrong starting status → `EmploymentNotActive`. Events `SUSPENSION` / `REINSTATEMENT`.

**exit** — already `EXITED` → `EmploymentNotActive`. `$on` < `hire_date` → `OutsideEmploymentPeriod`. `$on` earlier than the start of the latest assignment → `OutsideEmploymentPeriod` ("An assignment change exists after this date."). Sets `status = EXITED`, `exit_date`, `exit_reason`, `exit_remarks`; `EffectiveDating::close(Assignment::class, $e->id, $on)`. Other employees who report to this person are left unchanged — Task 11 reports them. Event `EXIT`. One transaction. Enrolment closing is added in Task 10.

**transferToSisterCompany** — `$to` must differ from `$from->company_id` → else `InvalidEmploymentData`. In **one transaction**: `exit($from, $lastDay, SISTER_COMPANY_TRANSFER)`; then `hire` into `$to` with `hire_date = $lastDay + 1 day`, `type` and `epf_liable` copied from `$from`, `confirmed_on` copied, `epf_no` from `$newEmployment`, `previous_employment_id = $from->id`, a **new** `employee_no` from `$to`'s sequence. Replace the plain `EXIT`/`HIRE` events with `SISTER_COMPANY_TRANSFER_OUT` on the old and `SISTER_COMPANY_TRANSFER_IN` on the new, each with `related_employment_id` pointing at the other. If anything fails, nothing is saved.

**OrgUnitService::hasCurrentAssignments** — true when any assignment effective today on a non-`EXITED` employment uses one of the ids.

**Company prefix lock** — `employee_no_prefix` and `employee_no_padding` cannot change once the company has any employment (enforce in the Companies form from Task 06; throw `InvalidEmploymentData`).

**EmploymentRequestService::request**

- New permission `employee.apply-directly`, granted to `super-admin` and `hr-manager`.
- User has it → call the matching `EmploymentService` / `PersonService` method now, inside `AuditLogger::withReason($data['reason'] ?? $operation, …)`, return null.
- Otherwise → `ApprovalService::submit` with definition `EMPLOYEE_EXIT` for `EXIT` and `SISTER_TRANSFER`, `EMPLOYEE_CHANGE` for the rest; `subjectType = Employment::class`, `subjectId = $e->id`, `action = UPDATE`, `payload = ['operation' => $operation] + $data`; return the request. Nothing else is written.
- Required permission per operation (else `AuthorizationException`): `EXIT`, `SISTER_TRANSFER`, `SUSPEND`, `REINSTATE` → `employee.exit`; others → `employee.update`.
- Payload keys per operation: `CONFIRM {on}`, `CHANGE_ASSIGNMENT {from, changes, reason}`, `UPDATE_PERSON {person: {...}}`, `SUSPEND {on, remarks}`, `REINSTATE {on}`, `EXIT {on, reason, remarks}`, `SISTER_TRANSFER {to_company_id, last_day, assignment, new_employment}`.
- The handlers (`EmployeeChangeHandler`, `EmployeeExitHandler`) read `payload['operation']` and call the same service methods; they set `approval_request_id` on the event they create.

## Steps

- [ ] 1. Enums, migrations, models, factories, `HrFixtures`. Test: hire tests.
- [ ] 2. `hire` and `rejoin`.
- [ ] 3. `confirm`, `changeAssignment`.
- [ ] 4. `suspend`, `reinstate`, `exit`; `hasCurrentAssignments`.
- [ ] 5. `transferToSisterCompany`.
- [ ] 6. `EmploymentRequestService`, handlers, permission.

## Tests

`EmploymentServiceTest.php` (uses `HrFixtures`; `$this->svc = app(EmploymentService::class)`)

```php
public function test_hire_creates_number_first_assignment_and_event(): void
{
    $c = $this->company(['employee_no_prefix' => 'ECW', 'employee_no_padding' => 3, 'next_employee_seq' => 1]);
    $e = $this->svc->hire($this->person(), $c, $this->emp(['hire_date' => '2026-12-01', 'type' => 'PROBATION']), $this->asg($c));

    $this->assertSame('ECW001', $e->employee_no);
    $this->assertSame(EmploymentStatus::ACTIVE, $e->status);
    $this->assertSame('2027-06-01', $e->confirmation_due_date->toDateString());
    $this->assertSame(1, $e->assignments()->count());
    $this->assertSame('2026-12-01', $e->assignments()->sole()->effective_from->toDateString());
    $this->assertNull($e->assignments()->sole()->effective_to);
    $this->assertSame(EventType::HIRE, $e->events()->sole()->type);
    $this->assertSame('2026-12-01', $e->events()->sole()->event_date->toDateString());
}

public function test_second_hire_in_company_gets_next_number(): void
{
    $c = $this->company(['employee_no_prefix' => 'ECW', 'next_employee_seq' => 1]);
    $this->hired(c: $c);
    $this->assertSame('ECW002', $this->hired(c: $c)->employee_no);
}

public function test_generator_skips_a_number_already_used_by_an_imported_employee(): void
{
    $c = $this->company(['employee_no_prefix' => 'ECW', 'next_employee_seq' => 1]);
    $this->svc->hire($this->person(), $c, $this->emp(['employee_no' => 'ECW001']), $this->asg($c));
    $this->assertSame('ECW002', $this->hired(c: $c)->employee_no);
}

public function test_person_cannot_hold_two_active_employments_even_across_companies(): void
{
    $p = $this->person(); $a = $this->company(); $b = $this->company();
    $this->svc->hire($p, $a, $this->emp(), $this->asg($a));
    $this->expectException(ActiveEmploymentExists::class);
    $this->svc->hire($p, $b, $this->emp(), $this->asg($b));
}

public function test_database_also_refuses_two_active_employments(): void
{
    $e = $this->hired();
    $this->expectException(\Illuminate\Database\QueryException::class);
    Employment::factory()->create(['person_id' => $e->person_id, 'status' => 'ACTIVE']);
}

public function test_intern_is_never_epf_liable(): void
{
    $c = $this->company();
    $e = $this->svc->hire($this->person(), $c, $this->emp(['type' => 'INTERN', 'epf_liable' => true, 'epf_no' => '123']), $this->asg($c));
    $this->assertFalse($e->epf_liable);
    $this->assertNull($e->epf_no);
}

public function test_epf_liable_without_epf_number_is_refused(): void
{
    $c = $this->company();
    $this->expectException(InvalidEmploymentData::class);
    $this->svc->hire($this->person(), $c, $this->emp(['epf_no' => null]), $this->asg($c));
}

public function test_epf_number_is_unique_within_a_company_but_may_repeat_across_companies(): void
{
    $a = $this->company(); $b = $this->company();
    $this->hired(c: $a, emp: ['epf_no' => '500']);
    $this->assertSame('500', $this->hired(c: $b, emp: ['epf_no' => '500'])->epf_no);
    $this->expectException(InvalidEmploymentData::class);
    $this->hired(c: $a, emp: ['epf_no' => '500']);
}

public function test_org_unit_of_another_company_is_refused(): void
{
    $a = $this->company(); $b = $this->company();
    $this->expectException(InvalidEmploymentData::class);
    $this->svc->hire($this->person(), $a, $this->emp(), $this->asg($b));
}

public function test_hire_date_too_far_in_the_future_is_refused(): void
{
    $this->travelTo('2026-12-01');
    $c = $this->company();
    $this->expectException(InvalidEmploymentData::class);
    $this->svc->hire($this->person(), $c, $this->emp(['hire_date' => '2027-02-01']), $this->asg($c));
}

public function test_confirm_turns_probation_into_permanent(): void
{
    $e = $this->hired('2026-06-01', emp: ['type' => 'PROBATION']);
    $e = $this->svc->confirm($e, '2026-12-01');
    $this->assertSame(EmploymentType::PERMANENT, $e->type);
    $this->assertSame('2026-12-01', $e->confirmed_on->toDateString());
    $this->assertSame(1, $e->events()->where('type', 'CONFIRMATION')->count());
}

public function test_confirming_a_permanent_employee_is_refused(): void
{
    $this->expectException(InvalidEmploymentData::class);
    $this->svc->confirm($this->hired(), '2026-12-01');
}

public function test_assignment_change_closes_previous_and_leaves_no_gap(): void
{
    $e = $this->hired('2026-06-01');
    $g1 = $e->currentAssignment()->grade_id; $g2 = Grade::factory()->create()->id;

    $new = $this->svc->changeAssignment($e, '2026-09-01', ['grade_id' => $g2], 'Promotion');

    $this->assertSame($g1, $e->assignmentOn('2026-08-31')->grade_id);
    $this->assertSame($g2, $e->assignmentOn('2026-09-01')->grade_id);
    $this->assertSame('2026-08-31', $e->assignments()->orderBy('effective_from')->first()->effective_to->toDateString());
    $this->assertSame('Promotion', $new->change_reason);
    $this->assertSame($e->assignments()->orderBy('effective_from')->first()->org_unit_id, $new->org_unit_id);   // unchanged fields copied
    $ev = $e->events()->where('type', 'ASSIGNMENT_CHANGE')->sole();
    $this->assertSame(['grade_id' => $g1], $ev->details['from']);
    $this->assertSame(['grade_id' => $g2], $ev->details['to']);
}

public function test_assignment_change_before_hire_date_is_refused(): void
{
    $e = $this->hired('2026-06-01');
    $this->expectException(OutsideEmploymentPeriod::class);
    $this->svc->changeAssignment($e, '2026-05-31', ['grade_id' => Grade::factory()->create()->id], 'x');
}

public function test_assignment_change_with_no_difference_is_refused(): void
{
    $e = $this->hired('2026-06-01');
    $this->expectException(InvalidEmploymentData::class);
    $this->svc->changeAssignment($e, '2026-09-01', ['grade_id' => $e->currentAssignment()->grade_id], 'x');
}

public function test_manager_must_be_active_in_the_same_company(): void
{
    $e = $this->hired('2026-06-01'); $outsider = $this->hired('2026-01-01');   // different company
    $this->expectException(InvalidEmploymentData::class);
    $this->svc->changeAssignment($e, '2026-09-01', ['manager_employment_id' => $outsider->id], 'x');
}

public function test_exit_sets_status_and_closes_assignment(): void
{
    $e = $this->hired('2026-01-01');
    $e = $this->svc->exit($e, '2026-10-31', ExitReason::RESIGNATION, 'Personal reasons');

    $this->assertSame(EmploymentStatus::EXITED, $e->status);
    $this->assertSame('2026-10-31', $e->exit_date->toDateString());
    $this->assertSame(ExitReason::RESIGNATION, $e->exit_reason);
    $this->assertSame('2026-10-31', $e->assignments()->sole()->effective_to->toDateString());
    $this->assertNull($e->assignmentOn('2026-11-01'));
    $this->assertSame(1, $e->events()->where('type', 'EXIT')->count());
}

public function test_exit_before_hire_date_is_refused(): void
{
    $this->expectException(OutsideEmploymentPeriod::class);
    $this->svc->exit($this->hired('2026-06-01'), '2026-05-31', ExitReason::RESIGNATION);
}

public function test_exit_before_a_later_assignment_change_is_refused(): void
{
    $e = $this->hired('2026-01-01');
    $this->svc->changeAssignment($e, '2026-09-01', ['grade_id' => Grade::factory()->create()->id], 'x');
    $this->expectException(OutsideEmploymentPeriod::class);
    $this->svc->exit($e, '2026-08-15', ExitReason::RESIGNATION);
}

public function test_exiting_twice_is_refused(): void
{
    $e = $this->svc->exit($this->hired('2026-01-01'), '2026-10-31', ExitReason::RESIGNATION);
    $this->expectException(EmploymentNotActive::class);
    $this->svc->exit($e, '2026-11-30', ExitReason::RESIGNATION);
}

public function test_rejoin_links_to_previous_employment_and_gets_a_new_number(): void
{
    $c = $this->company(); $p = $this->person();
    $old = $this->svc->exit($this->hired('2024-01-01', $c, p: $p), '2025-06-30', ExitReason::RESIGNATION);

    $new = $this->svc->rejoin($p, $c, $this->emp(['hire_date' => '2026-12-01']), $this->asg($c));

    $this->assertSame($old->id, $new->previous_employment_id);
    $this->assertNotSame($old->employee_no, $new->employee_no);
    $this->assertSame(EventType::REJOIN, $new->events()->sole()->type);
    $this->assertSame(2, Employment::where('person_id', $p->id)->count());
}

public function test_suspend_and_reinstate(): void
{
    $e = $this->svc->suspend($this->hired(), '2026-11-01', 'Inquiry');
    $this->assertSame(EmploymentStatus::SUSPENDED, $e->status);
    $this->assertSame(EmploymentStatus::ACTIVE, $this->svc->reinstate($e, '2026-11-15')->status);
}

public function test_org_unit_with_current_assignments_cannot_be_deactivated(): void
{
    $e = $this->hired();
    $this->expectException(OrgUnitInUse::class);
    app(OrgUnitService::class)->deactivate(OrgUnit::find($e->currentAssignment()->org_unit_id));
}

public function test_org_unit_can_be_deactivated_once_everyone_has_exited(): void
{
    $e = $this->hired('2026-01-01');
    $unit = OrgUnit::find($e->currentAssignment()->org_unit_id);
    $this->travelTo('2026-12-01');
    $this->svc->exit($e, '2026-10-31', ExitReason::RESIGNATION);
    app(OrgUnitService::class)->deactivate($unit);
    $this->assertFalse($unit->fresh()->is_active);
}
```

`SisterCompanyTransferTest.php`

```php
public function test_transfer_creates_linked_second_employment(): void
{
    $a = $this->company(['employee_no_prefix' => 'ECW']); $b = $this->company(['employee_no_prefix' => 'ECN', 'next_employee_seq' => 40]);
    $old = $this->hired('2024-03-01', $a, ['epf_no' => '500', 'type' => 'PERMANENT']);

    $new = $this->svc->transferToSisterCompany($old, $b, '2026-11-30', $this->asg($b), ['epf_no' => '77']);
    $old->refresh();

    $this->assertSame(EmploymentStatus::EXITED, $old->status);
    $this->assertSame('2026-11-30', $old->exit_date->toDateString());
    $this->assertSame(ExitReason::SISTER_COMPANY_TRANSFER, $old->exit_reason);

    $this->assertSame($b->id, $new->company_id);
    $this->assertSame($old->person_id, $new->person_id);
    $this->assertSame($old->id, $new->previous_employment_id);
    $this->assertSame('2026-12-01', $new->hire_date->toDateString());
    $this->assertSame('ECN040', $new->employee_no);
    $this->assertSame('77', $new->epf_no);
    $this->assertSame(EmploymentType::PERMANENT, $new->type);

    $out = $old->events()->where('type', 'SISTER_COMPANY_TRANSFER_OUT')->sole();
    $in = $new->events()->where('type', 'SISTER_COMPANY_TRANSFER_IN')->sole();
    $this->assertSame($new->id, $out->related_employment_id);
    $this->assertSame($old->id, $in->related_employment_id);
    $this->assertSame(0, $old->events()->where('type', 'EXIT')->count());
    $this->assertSame(0, $new->events()->where('type', 'HIRE')->count());
}

public function test_failed_transfer_leaves_the_old_employment_untouched(): void
{
    $a = $this->company(); $b = $this->company();
    $this->hired(c: $b, emp: ['epf_no' => '77']);                 // EPF 77 already used in B
    $old = $this->hired('2024-03-01', $a, ['epf_no' => '500']);

    try { $this->svc->transferToSisterCompany($old, $b, '2026-11-30', $this->asg($b), ['epf_no' => '77']); $this->fail(); }
    catch (InvalidEmploymentData) {}

    $old->refresh();
    $this->assertSame(EmploymentStatus::ACTIVE, $old->status);
    $this->assertNull($old->exit_date);
    $this->assertNull($old->assignments()->sole()->effective_to);
    $this->assertSame(1, Employment::where('person_id', $old->person_id)->count());
}

public function test_transfer_to_the_same_company_is_refused(): void
{
    $a = $this->company(); $old = $this->hired(c: $a);
    $this->expectException(InvalidEmploymentData::class);
    $this->svc->transferToSisterCompany($old, $a, '2026-11-30', $this->asg($a), ['epf_no' => '9']);
}

public function test_transferred_intern_stays_non_epf(): void
{
    $a = $this->company(); $b = $this->company();
    $old = $this->hired('2026-06-01', $a, ['type' => 'INTERN']);
    $new = $this->svc->transferToSisterCompany($old, $b, '2026-11-30', $this->asg($b));
    $this->assertFalse($new->epf_liable);
    $this->assertSame(EmploymentType::INTERN, $new->type);
}
```

`EmploymentRequestServiceTest.php` — users via `User::factory()->forCompanies([$c])->withRole(...)`.

```php
public function test_hr_manager_change_is_applied_directly(): void
{
    $e = $this->hired('2026-06-01'); $g2 = Grade::factory()->create()->id;
    $res = $this->req->request($this->user('hr-manager', $e), 'CHANGE_ASSIGNMENT', $e, ['from' => '2026-09-01', 'changes' => ['grade_id' => $g2], 'reason' => 'Promotion']);
    $this->assertNull($res);
    $this->assertSame($g2, $e->assignmentOn('2026-09-01')->grade_id);
}

public function test_hr_officer_change_creates_a_pending_request_and_changes_nothing(): void
{
    $e = $this->hired('2026-06-01'); $g1 = $e->currentAssignment()->grade_id; $g2 = Grade::factory()->create()->id;
    $res = $this->req->request($this->user('hr-officer', $e), 'CHANGE_ASSIGNMENT', $e, ['from' => '2026-09-01', 'changes' => ['grade_id' => $g2], 'reason' => 'Promotion']);

    $this->assertSame(ApprovalStatus::PENDING, $res->status);
    $this->assertSame('CHANGE_ASSIGNMENT', $res->payload['operation']);
    $this->assertSame(1, $e->assignments()->count());
    $this->assertSame($g1, $e->assignmentOn('2026-09-01')->grade_id);
}

public function test_approval_applies_the_change_with_the_requested_date_and_links_the_event(): void
{
    $e = $this->hired('2026-06-01'); $g2 = Grade::factory()->create()->id;
    $res = $this->req->request($this->user('hr-officer', $e), 'CHANGE_ASSIGNMENT', $e, ['from' => '2026-09-01', 'changes' => ['grade_id' => $g2], 'reason' => 'Promotion']);

    app(ApprovalService::class)->approve($res, $this->user('hr-manager', $e));

    $this->assertSame($g2, $e->assignmentOn('2026-09-01')->grade_id);
    $this->assertSame($res->id, $e->events()->where('type', 'ASSIGNMENT_CHANGE')->sole()->approval_request_id);
}

public function test_exit_request_uses_the_exit_definition(): void
{
    $e = $this->hired('2026-01-01');
    $res = $this->req->request($this->user('hr-officer', $e, ['employee.exit']), 'EXIT', $e, ['on' => '2026-10-31', 'reason' => 'RESIGNATION', 'remarks' => null]);
    $this->assertSame('EMPLOYEE_EXIT', $res->definition->code);
    $this->assertSame(EmploymentStatus::ACTIVE, $e->fresh()->status);
}

public function test_user_without_exit_permission_cannot_request_an_exit(): void
{
    $e = $this->hired('2026-01-01');
    $this->expectException(\Illuminate\Auth\Access\AuthorizationException::class);
    $this->req->request($this->user('hr-officer', $e), 'EXIT', $e, ['on' => '2026-10-31', 'reason' => 'RESIGNATION']);
}

public function test_person_update_goes_through_the_same_path(): void
{
    $e = $this->hired();
    $res = $this->req->request($this->user('hr-officer', $e), 'UPDATE_PERSON', $e, ['person' => ['calling_name' => 'Sunil']]);
    $this->assertNull($e->person->fresh()->calling_name);
    app(ApprovalService::class)->approve($res, $this->user('hr-manager', $e));
    $this->assertSame('Sunil', $e->person->fresh()->calling_name);
}
```

(`user(string $role, Employment $e, array $extraPermissions = [])` = a user for `$e`'s company with that role plus `approval.act` and the extras; call `$this->actingAs()` is **not** needed — the service receives the user.)

## Done when

All tests pass; suite and Pint clean.

## Do not

- Do not build screens.
- Do not delete employments, assignments or events anywhere.
- Do not let `employee_no` be changed after creation.
- Do not carry leave or service across the sister-company transfer — that policy is undecided.
