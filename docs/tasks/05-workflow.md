# Task 05 — Workflow (approval requests)

**Depends on:** 02, 03. **Read first:** `AGENTS.md`.

## Goal

One approval engine for the whole system. A user submits a request carrying the proposed change; approvers act step by step; on final approval a handler applies the change. Phase 1 uses it for employee changes and exits.

## Files

- Create module `Modules/Workflow`: models `ApprovalDefinition`, `ApprovalDefinitionStep`, `ApprovalRequest`, `ApprovalAction`; enums `ApproverType`, `ApprovalStatus`, `ApprovalActionType`, `Decision`; `app/Services/ApprovalService.php`; `app/Contracts/AppliesApproval.php`; exceptions `NotAllowedToAct`, `RequestAlreadyDecided`; `app/Livewire/MyApprovals.php` + view; routes; factories; migrations; seeder `ApprovalDefinitionSeeder`.
- Create: `tests/Support/Probe/ProbeApprovalHandler.php`.
- Test: `Modules/Workflow/tests/Feature/ApprovalServiceTest.php`, `MyApprovalsScreenTest.php`.

## Schema

**`approval_definitions`** — `code` string(50) unique, `name`, `is_active` boolean default true, timestamps.

**`approval_definition_steps`** — `approval_definition_id` FK cascade, `sequence` unsigned tinyint, `approver_type` string(10), `approver_ref` string(100) null, unique (`approval_definition_id`, `sequence`).

**`approval_requests`** — `approval_definition_id` FK, `company_id` FK, `subject_type` string(150), `subject_id` unsigned bigint null, `action` string(10), `payload` json, `status` string(10) default `PENDING`, `current_step` unsigned tinyint default 1, `requested_by` FK users, `requested_at` datetime, `decided_at` datetime null, timestamps. Index (`status`, `company_id`). Uses `BelongsToCompany`.

**`approval_actions`** — `approval_request_id` FK cascade, `step` unsigned tinyint, `user_id` FK users, `decision` string(10), `remarks` text null, `acted_at` datetime.

## Enums

- `ApproverType`: `ROLE` (ref = role name), `USER` (ref = user id), `MANAGER` (reserved — treat as "nobody can act" until Task 09 adds managers).
- `ApprovalStatus`: `PENDING`, `APPROVED`, `REJECTED`, `CANCELLED`.
- `ApprovalActionType`: `CREATE`, `UPDATE`, `DELETE`.
- `Decision`: `APPROVED`, `REJECTED`.

## Seed

| Code | Name | Steps |
|---|---|---|
| `EMPLOYEE_CHANGE` | Employee data change | 1: `ROLE` `hr-manager` |
| `EMPLOYEE_EXIT` | Employee exit or transfer | 1: `ROLE` `hr-manager` |

## Interfaces (must match exactly)

```php
namespace Modules\Workflow\Contracts;
interface AppliesApproval { public function apply(\Modules\Workflow\Models\ApprovalRequest $request): void; }

namespace Modules\Workflow\Services;
final class ApprovalService
{
    public static function registerHandler(string $definitionCode, string $handlerClass): void;

    public function submit(string $definitionCode, int $companyId, string $subjectType, ?int $subjectId,
                           ApprovalActionType $action, array $payload, User $by): ApprovalRequest;

    public function approve(ApprovalRequest $request, User $by, ?string $remarks = null): ApprovalRequest;
    public function reject(ApprovalRequest $request, User $by, string $remarks): ApprovalRequest;
    public function cancel(ApprovalRequest $request, User $by): ApprovalRequest;

    public function canAct(ApprovalRequest $request, User $user): bool;

    /** PENDING requests the user can act on now, within their company scope. */
    public function pendingFor(User $user): \Illuminate\Support\Collection;
}
```

## Rules

- `submit`: unknown or inactive definition → `\InvalidArgumentException`. A definition with **zero steps** is approved immediately in the same transaction: status `APPROVED`, `decided_at` set, handler applied, no action rows.
- `canAct` is true only when all hold: status `PENDING`; user is not the requester; user's `CompanyContext::permittedIds()` contains the request's company; user has permission `approval.act`; user matches the **current** step (`ROLE` → `hasRole(ref)`; `USER` → id equals ref).
- `approve`: check in this order — status not `PENDING` → `RequestAlreadyDecided`; then `canAct` false → `NotAllowedToAct`. Writes an action row. If more steps remain → `current_step + 1`. If it was the last step → in **one transaction**: call the handler's `apply()`, set `APPROVED` and `decided_at`. If the handler throws, nothing is saved (no action row, status stays `PENDING`) and the exception propagates.
- `reject`: same checks; empty `remarks` → `\InvalidArgumentException`; writes an action row; status `REJECTED`; handler **not** called.
- `cancel`: only the requester, only while `PENDING` → `CANCELLED`.
- A definition with no registered handler → final approval throws `\LogicException`.
- The handler is resolved from the container and wrapped in `AuditLogger::withReason("Approved request #{id}", …)`.

## Screen — My approvals (`approvals.index`, permission `approval.act`)

- Table: request no., type (definition name), company, requested by, requested at, summary line.
- Row opens a panel: a two-column table of each key in `payload` — "Current" (from the subject, when it exists) and "Requested" — with changed rows highlighted; Approve button (optional remarks); Reject button (remarks required).
- Tabs: "Waiting for me" (default), "My requests" (requests the user submitted, any status, with Cancel on pending ones).
- Navbar shows a count badge of "Waiting for me".

## Tests

`tests/Support/Probe/ProbeApprovalHandler.php` — implements `AppliesApproval`; has `public static int $calls = 0; public static bool $shouldFail = false;`; `apply()` increments `$calls`, throws `\RuntimeException('boom')` when `$shouldFail`, otherwise updates the `ScopedProbe` with id `subject_id` using `payload`.

Test `setUp`: reset the two static properties; create company `$c`; create definition `PROBE` with the steps each test needs; `ApprovalService::registerHandler('PROBE', ProbeApprovalHandler::class)`; `$probe = ScopedProbe::factory()->create(['company_id' => $c->id, 'name' => 'A'])`; users via a helper `user(string $role)` = `User::factory()->forCompanies([$c])->withRole($role)->withPermissions('approval.act')->create()`.

`Modules/Workflow/tests/Feature/ApprovalServiceTest.php`

```php
public function test_single_step_request_applies_on_approval(): void
{
    $this->steps([['ROLE', 'hr-manager']]);
    $req = $this->svc->submit('PROBE', $this->c->id, ScopedProbe::class, $this->probe->id, ApprovalActionType::UPDATE, ['name' => 'B'], $this->user('hr-officer'));
    $this->assertSame(ApprovalStatus::PENDING, $req->status);
    $this->assertSame('A', $this->probe->fresh()->name);

    $req = $this->svc->approve($req, $this->user('hr-manager'));

    $this->assertSame(ApprovalStatus::APPROVED, $req->status);
    $this->assertNotNull($req->decided_at);
    $this->assertSame('B', $this->probe->fresh()->name);
    $this->assertSame(1, ProbeApprovalHandler::$calls);
}

public function test_requester_cannot_approve_own_request(): void
{
    $this->steps([['ROLE', 'hr-manager']]);
    $manager = $this->user('hr-manager');
    $req = $this->submitBy($manager);

    $this->assertFalse($this->svc->canAct($req, $manager));
    $this->expectException(NotAllowedToAct::class);
    $this->svc->approve($req, $manager);
}

public function test_two_step_request_needs_both_steps_in_order(): void
{
    $this->steps([['ROLE', 'hr-manager'], ['ROLE', 'super-admin']]);
    $req = $this->submitBy($this->user('hr-officer'));
    $admin = $this->user('super-admin');

    $this->assertFalse($this->svc->canAct($req, $admin));          // not their step yet

    $req = $this->svc->approve($req, $this->user('hr-manager'));
    $this->assertSame(ApprovalStatus::PENDING, $req->status);
    $this->assertSame(2, $req->current_step);
    $this->assertSame(0, ProbeApprovalHandler::$calls);

    $req = $this->svc->approve($req, $admin);
    $this->assertSame(ApprovalStatus::APPROVED, $req->status);
    $this->assertSame(1, ProbeApprovalHandler::$calls);
    $this->assertSame(2, $req->actions()->count());
}

public function test_user_without_the_step_role_cannot_act(): void
{
    $this->steps([['ROLE', 'hr-manager']]);
    $req = $this->submitBy($this->user('hr-officer'));
    $this->expectException(NotAllowedToAct::class);
    $this->svc->approve($req, $this->user('payroll-officer'));
}

public function test_user_of_another_company_cannot_act(): void
{
    $this->steps([['ROLE', 'hr-manager']]);
    $req = $this->submitBy($this->user('hr-officer'));
    $outsider = User::factory()->forCompanies([Company::factory()->create()])->withRole('hr-manager')->withPermissions('approval.act')->create();
    $this->assertFalse($this->svc->canAct($req, $outsider));
}

public function test_reject_requires_remarks_and_does_not_apply(): void
{
    $this->steps([['ROLE', 'hr-manager']]);
    $req = $this->submitBy($this->user('hr-officer'));
    $manager = $this->user('hr-manager');

    try { $this->svc->reject($req, $manager, ''); $this->fail(); } catch (\InvalidArgumentException) {}

    $req = $this->svc->reject($req, $manager, 'Wrong department');
    $this->assertSame(ApprovalStatus::REJECTED, $req->status);
    $this->assertSame('A', $this->probe->fresh()->name);
    $this->assertSame(0, ProbeApprovalHandler::$calls);
}

public function test_decided_request_cannot_be_acted_on_again(): void
{
    $this->steps([['ROLE', 'hr-manager']]);
    $req = $this->svc->approve($this->submitBy($this->user('hr-officer')), $this->user('hr-manager'));
    $this->expectException(RequestAlreadyDecided::class);
    $this->svc->approve($req, $this->user('hr-manager'));
}

public function test_failing_handler_leaves_request_pending_and_nothing_saved(): void
{
    $this->steps([['ROLE', 'hr-manager']]);
    $req = $this->submitBy($this->user('hr-officer'));
    ProbeApprovalHandler::$shouldFail = true;

    try { $this->svc->approve($req, $this->user('hr-manager')); $this->fail(); } catch (\RuntimeException $e) { $this->assertSame('boom', $e->getMessage()); }

    $this->assertSame(ApprovalStatus::PENDING, $req->fresh()->status);
    $this->assertSame(0, $req->actions()->count());
    $this->assertSame('A', $this->probe->fresh()->name);
}

public function test_definition_with_no_steps_applies_immediately(): void
{
    $this->steps([]);
    $req = $this->submitBy($this->user('hr-officer'));
    $this->assertSame(ApprovalStatus::APPROVED, $req->status);
    $this->assertSame('B', $this->probe->fresh()->name);
}

public function test_only_requester_can_cancel_and_only_while_pending(): void
{
    $this->steps([['ROLE', 'hr-manager']]);
    $officer = $this->user('hr-officer');
    $req = $this->submitBy($officer);

    try { $this->svc->cancel($req, $this->user('hr-manager')); $this->fail(); } catch (NotAllowedToAct) {}
    $this->assertSame(ApprovalStatus::CANCELLED, $this->svc->cancel($req, $officer)->status);
}

public function test_approved_change_is_audited_with_the_request_number(): void
{
    $this->steps([['ROLE', 'hr-manager']]);
    $req = $this->svc->approve($this->submitBy($this->user('hr-officer')), $this->user('hr-manager'));
    $log = AuditLog::where('auditable_type', ScopedProbe::class)->where('event', 'UPDATED')->sole();
    $this->assertSame("Approved request #{$req->id}", $log->reason);
}
```

(`submitBy($user)` = `submit('PROBE', company, ScopedProbe::class, probe id, UPDATE, ['name' => 'B'], $user)`.)

`MyApprovalsScreenTest.php`

```php
public function test_screen_needs_permission(): void            // user without approval.act -> 403
public function test_waiting_list_shows_only_requests_the_user_can_act_on(): void
// 3 requests: one for the user's role+company, one for another role, one submitted by the user -> exactly 1 listed
public function test_reject_without_remarks_shows_a_validation_error(): void
```

## Done when

All tests pass; suite and Pint clean; by hand, a request created with tinker appears under "Waiting for me" for an `hr-manager` and disappears after approval.

## Do not

- Do not build employee-specific handlers here — Task 09.
- Do not add escalation, delegation or e-mail. Later phase.
