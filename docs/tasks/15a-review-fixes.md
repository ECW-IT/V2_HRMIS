# Task 15a — Fixes from the Phase 1 review

**Depends on:** 01–15. **Read first:** `AGENTS.md`, `docs/hrmis_v2/07_phase_1_code_review.md`.

## Goal

Correct the defects found when the Phase 0–1 build was reviewed. No new features. Do the steps in order; each step is small and has its own tests. Do this before Task 16.

This task changes interfaces that Tasks 02 and 15 fixed. Where this file and those files differ, this file applies.

## Files

- Modify: `Modules/Workflow/app/Services/ApprovalService.php`.
- Modify: `Modules/LegacyImport` — `LegacySyncExporter`, `ExportLegacySyncCommand`, `LegacySyncRun`, `LegacySyncIndex` and its view; new migration `add_company_id_to_legacy_sync_runs`.
- Modify: `Modules/AccessControl/app/Livewire/Users/UserList.php`, `Roles/RoleList.php`, `Roles/RolePermissions.php` and their views; `app/Models/User.php`; `database/seeders/RolesAndPermissionsSeeder.php`.
- Modify: `Modules/CoreHr/app/Services/EmploymentService.php`; `Modules/Attendance/app/Models/Device.php`; `Modules/Attendance/app/Services/EnrolmentService.php`; `app/Support/Audit/Auditable.php`; `app/Livewire/Login.php`; `Modules/CoreHr/routes/web.php`; `database/seeders/DatabaseSeeder.php`; `database/seeders/AdminUserSeeder.php`; `CLAUDE.md`.
- Delete: in every module, `routes/api.php` content that uses `auth:sanctum`; the scaffold `app/Http/Controllers/{AccessControl,CoreHr,LegacyImport,Organisation,Workflow}Controller.php`; `resources/views/index.blade.php`; `resources/views/components/layouts/master.blade.php` if nothing else uses it. Keep `Modules/Attendance/routes/api.php`.
- Tests: add to the existing test classes named in each step.

## Steps

### 1. An approval cannot be applied twice

In `approve()` and `reject()`, after the row is read with `lockForUpdate()` inside the transaction, check again: status is `PENDING`, else `RequestAlreadyDecided`; `canAct($request, $by)` is true, else `NotAllowedToAct`. `cancel()` runs in a transaction, locks the row, and re-checks the same way.

Add to `ApprovalServiceTest`:

```php
public function test_stale_copy_of_a_decided_request_cannot_be_approved_again(): void
// submit a one-step request; $stale = ApprovalRequest::find($r->id) (still PENDING in memory);
// first approver approves $r; second approver approves $stale -> RequestAlreadyDecided;
// the handler ran exactly once (count it the way the existing tests do with ProbeApprovalHandler);
// approval_actions has exactly one row.

public function test_stale_copy_of_a_decided_request_cannot_be_rejected_or_cancelled(): void
// approve $r; reject($stale, ...) -> RequestAlreadyDecided; cancel($stale, requester) -> NotAllowedToAct;
// status stays APPROVED.
```

### 2. Transition export is per company

- Migration: `legacy_sync_runs.company_id` FK `companies`, not null. The migration first deletes existing rows (they are hand-over lists, not business records); their files may stay on disk.
- `LegacySyncRun` uses `BelongsToCompany`.
- New signatures (replace Task 15's):

```php
public function run(Company $company, ?\DateTimeInterface $since = null): LegacySyncRun;
public function collect(Company $company, \DateTimeInterface $from, \DateTimeInterface $to): array;
```

- `collect` returns only rows whose employment belongs to `$company`. The "previous run" used for the window is the previous run **of that company**. Remove `withoutGlobalScopes()` from the exporter; filter by `company_id` explicitly instead.
- File name: `legacy-sync/legacy-sync-{company code}-{Ymd-His}.xlsx`.
- Command: `hrmis:export:legacy-sync {--since=} {--company=}` — runs once per active company, or only the company with that code.
- Screen: "Run now" needs a selected company (`CompanyContext::id()`); with none selected, show "Select a company first." The list, download and "Mark as applied" see only runs the company scope allows. Add a Company column.
- A sister-company transfer now appears as an exit in the old company's file and a hire in the new company's file.

Change the existing tests in `LegacySyncExportTest` only as far as the new `Company` argument requires — same inputs, same expected rows. Add:

```php
public function test_run_contains_only_its_own_company(): void
// hire A1 in company A and B1 in company B inside the window; run($a) -> hires has A1 only, run->company_id = $a->id.

public function test_user_of_one_company_cannot_see_or_download_another_companys_run(): void
// run($a) and run($b); loginAs('payroll-officer', $a); the screen lists run A only;
// calling download(run B id) -> ModelNotFoundException / 404.

public function test_each_company_has_its_own_window(): void
// run($a) at 10:00; hire in B at 10:05; hire in A at 10:06; run($b) at 10:10 -> B's hire, from = start of day;
// run($a) at 10:10 -> A's 10:06 hire only.

public function test_command_runs_once_per_active_company(): void
// two active companies, one inactive -> 2 runs.
```

### 3. User administration needs its own permission and is audited

- New permission `access-control.manage`, granted to `super-admin` only (add it to the `hr-manager` exclusion list in `RolesAndPermissionsSeeder`).
- Every method that writes — creating or editing a user, setting a password, assigning roles or companies, creating or renaming a role, saving a role's permissions — calls `$this->authorize('access-control.manage')`. Viewing stays on `access-control.view`. Hide the write buttons from users without it.
- `App\Models\User` uses `Auditable` with `protected array $auditExcluded = ['password', 'remember_token'];`.
- When a user's roles or companies change, or a role's permissions change, write one `audit_logs` row: `event` `UPDATED`, `auditable` = the user or the role, `old_values` / `new_values` = `['roles' => [...]]`, `['companies' => [...]]` or `['permissions' => [...]]` (sorted names or codes). Only when the list really changed.
- A user may not remove their own `super-admin` role or deactivate themself ("You cannot remove your own access.").

Add `Modules/AccessControl/tests/Feature/UserAdministrationTest.php`:

```php
public function test_view_permission_alone_cannot_create_or_edit_users(): void
// a role with access-control.view only: the Users screen loads; calling save() -> AuthorizationException.
public function test_view_permission_alone_cannot_change_role_permissions(): void
public function test_creating_a_user_is_audited_without_the_password(): void
// audit row CREATED for User; new_values has 'email', has no 'password' key.
public function test_role_and_company_changes_are_audited(): void
// change roles ['hr-officer'] -> ['hr-manager']: one row with old ['roles' => ['hr-officer']], new ['roles' => ['hr-manager']].
public function test_saving_without_changes_writes_no_audit_row(): void
public function test_admin_cannot_remove_own_super_admin_role_or_deactivate_self(): void
```

### 4. Smaller corrections

| # | Change | Test (add to the class named) |
|---|---|---|
| a | `EmploymentService::createEmployment`: when the insert fails on the unique index `employments_active_person_id_unique`, throw `ActiveEmploymentExists` ("This person is already employed in another company."). Do not use `withoutGlobalScopes()`. | `EmploymentServiceTest::test_hiring_a_person_active_in_a_company_the_user_cannot_see_gives_a_clear_error` — hire in B; `loginAs('hr-manager', $a)`; hire the same person in A → `ActiveEmploymentExists`. |
| b | `recordEvent` sets `created_by` to `Auth::id()`. | `EmploymentServiceTest::test_events_record_who_created_them` — logged in: the `HIRE` event has the user's id; in console (no user): null. |
| c | `Device`: `protected array $auditExcluded = ['api_token_hash', 'last_seen_at'];` | `DeviceScreensTest::test_token_hash_is_never_written_to_the_audit_log` — register and rotate; no audit row's values contain the key `api_token_hash`. |
| d | `Auditable::normalizeAuditValue`: a date-time with a non-midnight time is written `Y-m-d H:i:s`; a date stays `Y-m-d`. | `AuditTest::test_datetime_values_keep_their_time` |
| e | `DatabaseSeeder` also calls `DocumentTypeSeeder` and `BankSeeder`. | `SmokeTest::test_database_seeder_seeds_document_types` — after `$this->seed()` (with `ADMIN_INITIAL_PASSWORD` set by `config`/`putenv` in the test), `DocumentType::count() > 0`. |
| f | `AdminUserSeeder`: if `admin@ecw.local` exists, leave the password and `must_change_password` alone; only make sure it has the `super-admin` role. | `SmokeTest::test_reseeding_does_not_reset_the_admin_password` |
| g | `Login`: an inactive account gets the same message as a wrong password. | `LoginTest::test_inactive_user_gets_the_generic_message` — adjust the existing inactive-user test's expected message; nothing else. |
| h | `documents.download` route adds `password.changed`. | — |
| i | `EnrolmentService::end`: an end date before `valid_from` → `\InvalidArgumentException`. `EmploymentService::suspend`: a date before the hire date → `OutsideEmploymentPeriod`. | `EnrolmentServiceTest::test_end_date_cannot_be_before_the_start`; `EmploymentServiceTest::test_suspend_before_hire_date_is_refused` |
| j | Delete the scaffold files listed under **Files**. `php artisan route:list` shows no route with `sanctum`. | `SmokeTest::test_no_route_uses_an_undefined_guard` — no registered route's middleware contains `auth:sanctum`. |
| k | Replace the content of `CLAUDE.md` with: `Follow AGENTS.md.` | — |

Item g changes an expected message in one existing test. That is the only existing assertion this task may change, apart from the `Company` argument in step 2.

## Done when

- All new tests pass; the whole suite and Pint are clean.
- `git grep -n "auth:sanctum"` and `git grep -n "withoutGlobalScopes" -- Modules/LegacyImport/app/Services/LegacySyncExporter.php` return nothing.
- Logged in as a one-company `payroll-officer`, the Legacy sync screen shows that company's runs only.

## Do not

- Do not install Sanctum or any other package.
- Do not change what the export sheets contain, only which company's rows go in which file.
- Do not edit committed migrations; add new ones.
- Do not touch tests other than as stated above.
