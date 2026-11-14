# HRMIS v2 — review of the Phase 0–1 build

**Reviewed:** `HRMIS_V2/` at commit `84f7a1f` ("V2_HRMIS", 2026-11-13), against `cursor_pack/tasks/01`–`15`.
**Date of review:** 2026-11-13.
**For:** project lead, Harshi, Avishka.

## 1. Summary

The build follows the pack closely. All fifteen tasks are present, the schema and service interfaces match the task files, and every one of the 277 test names the pack specifies exists in the code (322 tests in total).

The whole suite passes: 322 of 322 tests on MySQL.

It is not ready for real employee data yet. Three defects need fixing first (section 3) — the tests pass because none of them covers these cases — and the workshop outputs from Task 00 are still placeholders.

The fixes are packaged as a task Harshi can give to Cursor: `cursor_pack/tasks/15a-review-fixes.md`.

## 2. What was checked, and what was not

| Check | Result |
|---|---|
| Every test named in tasks 01–15 exists | Yes — 277 of 277 |
| Skipped or hollowed-out tests (`markTestSkipped`, `assertTrue(true)`) | None found |
| `withoutGlobalScopes()` outside the import module | None in application code (one use in the transition export, see 3.2) |
| Laravel Pint (`./vendor/bin/pint --test`) | Passes |
| PHP syntax of every file | Passes |
| Unit tests that need no database (`NicTest`, 9 tests) | Pass |
| Installed versions | Laravel 13.42, Livewire 4.4.8, nwidart/laravel-modules 13.3, spatie/laravel-permission 8.5, PHPUnit 12.5 — as specified |
| Feature tests (the other 313) | **Pass** — run 2026-11-13 on MySQL 8.0.30, database `ecw_hrmis_v2_test`: 273 tests outside the import module (660 assertions) and 49 import tests (195 assertions). 322 of 322 in total. The import tests were pointed at the same database for their legacy fixture tables. |
| Screens in a browser | Not opened |

Read in full: company scope, audit trail, effective dating, workflow service, employment and request services, NIC rules, number generator, attendance ingest, enrolments, all route files, seeders, readiness registration, shared test fixtures.

Read only in part: the hire wizard (697 lines), the Blade views, and the import services (`Stager`, `Validator`, `Loader`). Their tests exist, but I did not check their rules line by line.

## 3. Fix before any real data is loaded

### 3.1 An approval can be applied twice

`Modules/Workflow/app/Services/ApprovalService.php`, `approve()` and `reject()`.

The status is checked before the request row is locked, and not again after. If two approvers press Approve at the same moment, both pass the check; the second waits for the lock and then applies the change a second time. For an exit or a sister-company transfer the second run fails with an error; for an assignment change it can write a duplicate.

**Confirmed by test.** A temporary test loaded a second copy of a pending request, approved the first copy, then approved the second: no exception, the handler ran twice, and two approval actions were recorded. (The test was removed afterwards; Task 15a adds it permanently.)

**Fix:** after `lockForUpdate()`, re-check `status === PENDING` and `canAct()`, and throw `RequestAlreadyDecided` if not. `cancel()` needs the same lock.

### 3.2 The transition export shows every company's data to a one-company user

`Modules/LegacyImport` — `LegacySyncExporter`, `LegacySyncIndex`, table `legacy_sync_runs`.

Export files hold new hires, exits and **bank account numbers**. A run has no company. The nightly run (console) covers all companies, and the screen lists and downloads every run to any user holding `employee.view` and `employee.bank.view` — for example a payroll officer who is permitted one company only. The bank-change section also reads employments with `withoutGlobalScopes()`.

This is a gap in the pack, not a build error: Task 15 said "covers all companies" and gave the run no company.

**Fix:** one run per company (`company_id` on `legacy_sync_runs`, `BelongsToCompany` on the model, the command loops over active companies, the file name carries the company code). The screen then shows only permitted companies' runs.

### 3.3 User administration is open to anyone who can view it, and is not audited

`Modules/AccessControl/app/Livewire/Users/UserList.php`, `Roles/RolePermissions.php`, `Roles/RoleList.php`.

Creating users, resetting passwords, assigning roles and editing a role's permissions are all guarded by `access-control.view`. There is no separate "manage" permission. Today only `super-admin` holds it, so there is no live exposure, but the first time someone is given view access they get full control.

`App\Models\User` does not use `Auditable`, and role and company assignments are not logged. Who gave whom access is exactly what an auditor asks.

This is partly a gap in the pack: Task 02 defined only `access-control.view`.

**Fix:** add `access-control.manage` (super-admin only) on every write action; make `User` auditable with `password` and `remember_token` excluded; write an audit row when roles, permissions or company access change.

## 4. Fix during Phase 2

| # | Finding | Where | Fix |
|---|---|---|---|
| 4.1 | Leftover scaffold API routes use `auth:sanctum`, which is not installed. Calling them gives a server error. Stub controllers and `index.blade.php` views are also left in every module. | `Modules/{AccessControl,Organisation,LegacyImport}/routes/api.php`, `*Controller.php` stubs, `resources/views/index.blade.php` | Delete them. |
| 4.2 | Document types and banks are never seeded by `php artisan migrate:fresh --seed`. A new database has no document types, so documents cannot be uploaded and check HR009 has nothing to test. (Pack gap: Task 10 did not say to call the seeders.) | `database/seeders/DatabaseSeeder.php` | Call `DocumentTypeSeeder` and `BankSeeder`. |
| 4.3 | Hiring a person who is active in a company the user cannot see fails with a database error, not a clear message. The data stays correct — the unique index stops it — but the user sees an error page. | `EmploymentService::createEmployment` | Catch the unique-index violation and throw `ActiveEmploymentExists` ("already employed in another company"). |
| 4.4 | `employment_events.created_by` is always null, so the employment history cannot show who recorded an event. (Pack gap: the column was specified, the value was not.) | `EmploymentService::recordEvent` | Set `Auth::id()`. |
| 4.5 | A device's token hash is written to the audit log on registration and rotation. | `Modules/Attendance/app/Models/Device.php` | `protected array $auditExcluded = ['api_token_hash', 'last_seen_at'];` |
| 4.6 | The repository's `CLAUDE.md` tells an AI agent to install Laravel Boost and to run remote install scripts. That contradicts `AGENTS.md` ("never add a package the task does not name"). | `CLAUDE.md` | Replace its content with one line: "Follow AGENTS.md." |
| 4.7 | `AGENTS.md` in the repository is the earlier version; `docs/GLOSSARY.md` and tasks 16–25 are not in the repository. | repository root, `docs/` | Copy from `cursor_pack/`. |

## 5. Minor

- **Audit of date-time values drops the time.** `Auditable::normalizeAuditValue` formats every date as `Y-m-d`. Use `Y-m-d H:i:s` when the value has a time part.
- **Document download** route has `auth` and the permission, but not `password.changed`.
- **Login** tells an inactive user "Your account is inactive", which confirms the address exists. Use the same message as a wrong password.
- **`AdminUserSeeder`** resets the admin password to the initial one every time it runs. Do not re-run the seeders on a live database; make the seeder skip an existing user.
- **Punch ingest** looks for existing rows with one query of up to 1,000 `OR` groups before inserting. The unique index and `insertOrIgnore` already do this; use the insert's row count for `inserted`. Not wrong, only slow on a large batch.
- **`EnrolmentService::end`** accepts an end date before the start date.
- **`suspend`** does not check that the date is on or after the hire date.

## 6. Process

- **One commit holds all fifteen tasks**, on a branch named `task/01-project-skeleton`, and `main` is the same commit. The pack asks for one branch per task and the acceptance checklist before each merge. With one commit nobody can see whether a specified test was changed after it was written. From Task 16 on: one branch and one pull request per task, with the test output pasted in.
- **Task 00 has not happened.** Companies are still `CO1` / `CO2` placeholders; there is no `docs/mapping/` folder and no bank CSV. The import (Task 14) and the readiness dashboard have therefore run only against test fixtures. The open points in `04_decisions.md` section 4 still stand.
- **The scheduler** now has two jobs (`attendance:resolve-pending` 01:00, `hrmis:export:legacy-sync` 18:00). The server needs the cron entry for `php artisan schedule:run`.

## 7. Effect on the Phase 2 pack

The code showed four places where the Phase 2 task files did not match what was built. The task files are corrected:

| Task | Correction |
|---|---|
| 20, 22 | Approval definitions are seeded in `database/seeders/ApprovalDefinitionSeeder.php`, not in a Workflow module seeder. That seeder deletes and re-creates each definition's steps on every run. |
| 22 | `ApproverType::MANAGER` already exists and returns false; the task now says to implement it, not add it. `approval_actions.user_id` is `NOT NULL` in the built schema, so the task adds a migration making it nullable (needed for the "step skipped" row). |
| 23, 25 | In `RolesAndPermissionsSeeder`, `hr-manager` receives every permission not on its exclusion list. Permissions meant for `super-admin` only (`period.reopen`, `api-client.manage`) must be added to that list. |
| Pack README | New task `15a` placed before Phase 2; note on the seeder pattern. |

Everything else Phase 2 relies on is present under the names the tasks use: `HrFixtures` (`company`, `person`, `hired`, `asg`, `emp`, `loginAs`), `Modules/CoreHr/config/readiness.php`, `PunchIngestService`, `PunchResolver`, `EnrolmentService`, `ApprovalService::submit`, the profile's Attendance tab, `routes/console.php`.
