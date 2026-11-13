# Task 13 — Employee screens

**Depends on:** 09, 10, 11 (12 for the Attendance tab — if Task 12 is not merged, leave that tab out and say so). **Read first:** `AGENTS.md`; `STYLES_GUIDE.md`; the list and form screens in `Modules/AccessControl` and `Modules/Organisation` as the pattern.

Work through the Steps one at a time and pause after each.

## Goal

The screens HR uses every day: find an employee, hire one, view and change a record, end an employment. Every action calls a service from Tasks 08–10; this task adds **no business rules**.

## Files

- `Modules/CoreHr/app/Livewire/`: `EmployeesIndex`, `HireWizard`, `EmployeeProfile`, and profile tab components `Tabs/Summary`, `Tabs/Personal`, `Tabs/EmploymentHistory`, `Tabs/Bank`, `Tabs/Documents`, `Tabs/Readiness`, `Tabs/Audit`; action components `Actions/ChangeAssignment`, `Actions/ConfirmEmployment`, `Actions/ExitEmployment`, `Actions/SisterTransfer`, `Actions/Enrol`.
- `Modules/CoreHr/app/Exports/EmployeesExport.php`; views; `routes/web.php`.
- Modify: main navigation (add "Employees").
- Tests: `Modules/CoreHr/tests/Feature/Screens/EmployeesIndexTest.php`, `HireWizardTest.php`, `EmployeeProfileTest.php`, `EmployeeActionsTest.php`.

## Routes

| Route name | URL | Component | Permission |
|---|---|---|---|
| `employees.index` | `/employees` | `EmployeesIndex` | `employee.view` |
| `employees.create` | `/employees/hire` | `HireWizard` | `employee.create` |
| `employees.show` | `/employees/{employment}` | `EmployeeProfile` | `employee.view` |

`{employment}` binds through the company scope, so another company's record gives 404.

## Screen 1 — Employee list

- **Filters:** search (employee no., name with initials, calling name, NIC — NIC search normalises the input with `Nic::normalise` when it parses, otherwise does a plain `like`); company (from the navbar selector); org unit (tree picker; includes all units beneath it); job; grade; type; status (default `ACTIVE`; options Active, Suspended, Exited, All).
- **Columns:** employee no., name with initials, department / section (current assignment's org unit and its parent), job, grade, type, hire date, status badge, readiness badge (red dot when the employee has any blocker today).
- Sorted by employee no. by default; sortable on no., name, hire date. 25 per page, server-side.
- **Buttons:** "Hire employee" (with `employee.create`); "Export to Excel" (current filters, all pages; columns as shown plus NIC and EPF no.; **bank details are never exported**).
- Row click → profile.
- Empty state: "No employees match these filters."

## Screen 2 — Hire wizard

Five steps; "Back" keeps what was entered; nothing is saved until the last step.

| Step | Fields and behaviour |
|---|---|
| 1. Identify | Company (required; default = selected company). NIC **or** passport no. On "Next": look up the person with `PersonService::findByNic` / passport. **Not found** → step 2 blank. **Found, with a non-exited employment** → stop with "This person is already employed (ECW035, Company One)." and a link to that profile. **Found, exited only** → show the past employments (company, number, hire and exit dates, exit reason) and continue as a **Rejoin** if a past employment is in the chosen company, otherwise as a new hire for the existing person; step 2 is pre-filled. |
| 2. Personal | Title, first name*, middle name, last name*, name with initials*, calling name, date of birth* and gender* (pre-filled from the NIC and read-only when a NIC was given), marital status, nationality, mobile*, email, permanent address (line 1*, line 2, city*, district, postal code), emergency contact (name, relationship, phone). |
| 3. Employment | Type* (Permanent, Probation, Intern, Contract); hire date* (default today); confirmation due date (shown for Probation, default hire + 6 months); EPF-liable (checkbox, default on; hidden and off for Intern); EPF no. (required when liable); department/section* (tree picker limited to the company); job*; grade*; location*; reports to (search active employees of the company). |
| 4. Bank and fingerprint | "Paid in cash" checkbox **or** bank*, branch* (filtered by bank), account no.*, account name* (default = name with initials). Device group and device user ID (both optional here; can be added later). |
| 5. Review | Read-only summary of everything, with the note "The employee number will be issued when you save." Button "Hire". |

On "Hire" — in one `DB::transaction`: `PersonService::create` (or `update` for an existing person) → `EmploymentService::hire` or `rejoin` → `BankAccountService::set` (effective from hire date) → `EnrolmentService::enrol` when an ID was given. Any domain exception is shown at the top of the review step, on the field it concerns where obvious, and nothing is saved. On success redirect to the profile with the flash "Hired as ECW412."

## Screen 3 — Profile

**Header (always visible):** photo, name with initials, employee no., company, job · department, type and status badges, hire date, length of service (this employment), and action buttons according to permission and status:

| Button | Shown when | Permission |
|---|---|---|
| Change assignment | not exited | `employee.update` |
| Confirm | active + probation | `employee.update` |
| Suspend / Reinstate | active / suspended | `employee.exit` |
| Transfer to sister company | active | `employee.exit` |
| Exit | not exited | `employee.exit` |

A yellow bar appears when the employee has pending approval requests: "2 changes are waiting for approval." linking to them.

**Tabs**

| Tab | Content |
|---|---|
| Summary | Key facts in two columns: personal (NIC, date of birth, age, gender, mobile), employment (type, hire date, confirmation due / confirmed on, EPF liable and no., grade, location, reports to), readiness issues today (list, or a green "Ready"). If the person has other employments, a "Other employments" list (company, number, dates) linking to those the user may see. |
| Personal | Editable groups: names; contacts (add/remove, mark primary); addresses; emergency contacts; dependents; qualifications; experience. "Save" sends the changes through `EmploymentRequestService::request(..., 'UPDATE_PERSON', ...)`. NIC, date of birth and gender are read-only here. |
| Employment and history | Timeline, newest first, merging `employment_events` and assignments: date, what happened ("Assignment change — Grade G3 → G2, reason: Promotion"), who, and the approval request no. when there is one. Below it, the assignment table: from, to, department/section, job, grade, location, reports to. |
| Bank | Needs `employee.bank.view`, otherwise the tab is hidden. Current account and history (from, to, bank, branch, account no., account name / "Cash"). "Change bank account" (needs `employee.bank.manage`): effective from, cash or bank fields → `BankAccountService::set`. |
| Documents | Needs `employee.document.view`. Table: type, file name, issued, expires (red when past, amber within 30 days), uploaded by/on, Download. "Upload" and "Archive" need `employee.document.manage`. Mandatory types not yet uploaded are listed at the top as "Missing". |
| Attendance | Needs `attendance.punch.view`. Current enrolments (group, device user ID, from, to) with "Enrol" (needs `attendance.device.manage`); the `EmployeePunches` component from Task 12 for a chosen month. |
| Readiness | Result of `ReadinessService::forEmployment` for a chosen date. |
| Audit | Needs `audit.view`. Audit rows for this employment, its person, assignments and bank accounts, newest first. Account numbers stay masked. |

## Action dialogs

All go through `EmploymentRequestService::request`. After a direct apply show "Saved."; after a submission show "Sent for approval (request #123)."

| Action | Fields | Operation |
|---|---|---|
| Change assignment | effective from* (default today; not before hire date); department/section, job, grade, location, reports to (pre-filled with current values); reason* | `CHANGE_ASSIGNMENT` — `changes` contains only the fields that differ |
| Confirm | confirmed on* (default = confirmation due date or today) | `CONFIRM` |
| Suspend | from*, remarks* | `SUSPEND` |
| Reinstate | on* | `REINSTATE` |
| Exit | last working day*, reason* (all `ExitReason` values **except** Sister company transfer), remarks | `EXIT` |
| Transfer to sister company | target company* (other active companies the user may see), last working day*, new department/section*, job*, grade*, location*, new EPF no. (required when EPF-liable). Shows: "A new employee number will be issued in the target company." | `SISTER_TRANSFER` |

## Steps

- [ ] 1. Routes, navigation, employee list with filters and export.
- [ ] 2. Profile header and Summary, Employment and history, Readiness, Audit tabs.
- [ ] 3. Personal, Bank, Documents, Attendance tabs.
- [ ] 4. Action dialogs.
- [ ] 5. Hire wizard.

## Tests

All use `HrFixtures` (including `bankWithBranch()` and `account()` added in Task 10). `$this->loginAs(string $role, Company ...$companies)` = `actingAs` a user with that role for those companies and `must_change_password = false`.

`EmployeesIndexTest.php`

```php
public function test_list_needs_employee_view(): void
{
    $this->actingAs(User::factory()->allCompanies()->create(['must_change_password' => false]))->get(route('employees.index'))->assertForbidden();
}

public function test_list_shows_only_active_employees_of_permitted_companies_by_default(): void
{
    $a = $this->company(); $b = $this->company();
    $active = $this->hired(c: $a); $other = $this->hired(c: $b);
    $exited = app(EmploymentService::class)->exit($this->hired('2026-01-01', $a), '2026-06-30', ExitReason::RESIGNATION);

    $this->loginAs('hr-officer', $a);
    Livewire::test(EmployeesIndex::class)->assertSee($active->employee_no)->assertDontSee($other->employee_no)->assertDontSee($exited->employee_no);
}

public function test_status_filter_shows_exited(): void          // ->set('status', 'EXITED') -> sees the exited number only
public function test_search_by_employee_number_name_and_nic_in_either_format(): void
// person with NIC 853400937V: ->set('search', '198534000937') finds them; ->set('search', '853400937v') finds them; ->set('search', $employee_no) finds them
public function test_org_unit_filter_includes_units_beneath_it(): void
// employee assigned to section PAINT under department PROD: ->set('orgUnitId', PROD) lists them; ->set('orgUnitId', other dept) does not
public function test_export_respects_filters_and_never_contains_bank_details(): void
// Excel::fake(); ->call('export'); Excel::assertDownloaded; the export's headings contain 'EPF no.' and do not contain 'Account'
```

`HireWizardTest.php`

```php
public function test_new_person_is_hired_with_bank_and_enrolment(): void
{
    $c = $this->company(['employee_no_prefix' => 'ECW', 'next_employee_seq' => 412]); $a = $this->asg($c); [$bank, $branch] = $this->bankWithBranch();
    $this->loginAs('hr-manager', $c);

    Livewire::test(HireWizard::class)
        ->set('companyId', $c->id)->set('nic', '853400937V')->call('next')
        ->assertSet('step', 2)->assertSet('dateOfBirth', '1985-12-05')->assertSet('gender', 'MALE')
        ->set('firstName', 'Nimal')->set('lastName', 'Perera')->set('nameWithInitials', 'N. Perera')->set('mobile', '0771234567')
        ->set('addressLine1', '12 Main St')->set('city', 'Nittambuwa')->call('next')
        ->set('type', 'PERMANENT')->set('hireDate', '2026-12-01')->set('epfNo', '900')
        ->set('orgUnitId', $a['org_unit_id'])->set('jobId', $a['job_id'])->set('gradeId', $a['grade_id'])->set('locationId', $a['location_id'])->call('next')
        ->set('bankId', $bank->id)->set('bankBranchId', $branch->id)->set('accountNo', '0012345678')->set('deviceGroup', 'NIT')->set('deviceUserId', 116)->call('next')
        ->assertSet('step', 5)->call('hire')
        ->assertHasNoErrors()->assertRedirect();

    $e = Employment::sole();
    $this->assertSame('ECW412', $e->employee_no);
    $this->assertSame('198534000937', $e->person->nic);
    $this->assertSame('N. Perera', app(BankAccountService::class)->on($e, '2026-12-01')->account_name);
    $this->assertSame($e->id, app(EnrolmentService::class)->resolve('NIT', 116, '2026-12-01 08:00:00'));
}

public function test_person_already_employed_is_stopped_at_step_one(): void
{
    $e = $this->hired(); $this->loginAs('hr-manager', $e->company);
    Livewire::test(HireWizard::class)->set('companyId', $e->company_id)->set('nic', $e->person->nic)->call('next')
        ->assertSet('step', 1)->assertSee('already employed')->assertSee($e->employee_no);
}

public function test_person_with_only_exited_employment_in_the_company_becomes_a_rejoin(): void
{
    $c = $this->company(); $p = $this->person();
    $old = app(EmploymentService::class)->exit($this->hired('2024-01-01', $c, p: $p), '2025-06-30', ExitReason::RESIGNATION);
    $this->loginAs('hr-manager', $c);

    Livewire::test(HireWizard::class)->set('companyId', $c->id)->set('nic', $p->nic)->call('next')
        ->assertSet('step', 2)->assertSet('mode', 'REJOIN')->assertSet('firstName', $p->first_name)->assertSee($old->employee_no);
}

public function test_intern_hides_epf_and_is_saved_non_liable(): void
// ->set('type', 'INTERN') -> assertSet('epfLiable', false); complete the wizard without EPF no. -> Employment::sole()->epf_liable === false
public function test_missing_required_fields_block_the_step(): void
// step 2 ->call('next') with no names -> assertHasErrors(['firstName', 'lastName', 'nameWithInitials', 'mobile'])
public function test_domain_error_on_save_shows_a_message_and_saves_nothing(): void
// EPF no. already used in the company -> ->call('hire') -> assertSee('EPF number is already in use'); Person::count() and Employment::count() unchanged
public function test_wizard_needs_employee_create(): void                 // hr role without it -> 403
public function test_company_picker_only_lists_permitted_companies(): void
```

`EmployeeProfileTest.php`

```php
public function test_profile_of_another_company_returns_404(): void
{
    $mine = $this->company(); $theirs = $this->hired(c: $this->company());
    $this->loginAs('hr-officer', $mine);
    $this->get(route('employees.show', $theirs))->assertNotFound();
}

public function test_profile_shows_header_and_summary(): void             // assertSee employee no., name with initials, job name, grade name
public function test_bank_tab_is_hidden_without_permission(): void
// hr-officer (no employee.bank.view): assertDontSee('0012345678') and assertDontSee('Bank') tab label; payroll-officer sees the tab
public function test_history_tab_lists_events_newest_first(): void
// hire 2026-01-01, assignment change 2026-06-01 -> assertSeeInOrder(['Assignment change', 'Hire'])
public function test_other_employments_of_the_person_are_listed_only_when_visible(): void
// transferred A -> B. User with A and B sees both numbers on either profile; user with only B does not see the A number
public function test_pending_requests_bar_is_shown(): void
public function test_audit_tab_needs_audit_view(): void
public function test_documents_tab_lists_missing_mandatory_types(): void
```

`EmployeeActionsTest.php`

```php
public function test_hr_officer_change_creates_pending_request_and_does_not_change_data(): void
{
    $e = $this->hired('2026-06-01'); $g1 = $e->currentAssignment()->grade_id; $g2 = Grade::factory()->create();
    $this->loginAs('hr-officer', $e->company);

    Livewire::test(ChangeAssignment::class, ['employment' => $e])
        ->set('effectiveFrom', '2026-09-01')->set('gradeId', $g2->id)->set('reason', 'Promotion')->call('save')
        ->assertHasNoErrors()->assertSee('Sent for approval');

    $this->assertSame($g1, $e->assignmentOn('2026-09-01')->grade_id);
    $req = ApprovalRequest::sole();
    $this->assertSame(['grade_id' => $g2->id], $req->payload['changes']);      // only the field that differs
}

public function test_hr_manager_change_is_applied_at_once(): void         // same as above as hr-manager -> assertSee('Saved'); grade changed; no ApprovalRequest
public function test_change_without_a_reason_is_a_validation_error(): void
public function test_exit_dialog_does_not_offer_sister_company_transfer_as_a_reason(): void
public function test_exit_needs_employee_exit_permission(): void          // hr-officer -> ->call('save') -> assertForbidden
public function test_sister_transfer_only_offers_other_permitted_companies(): void
public function test_sister_transfer_by_hr_manager_creates_the_new_employment_and_redirects_to_it(): void
// A -> B, last day 2026-11-30, EPF 77 -> new Employment in B with hire_date 2026-12-01; redirect to its profile
public function test_buttons_follow_status(): void
// exited employee: profile shows none of 'Change assignment', 'Exit', 'Transfer'; probation employee shows 'Confirm'; permanent does not
```

## Done when

- All tests pass; suite and Pint clean.
- By hand, as `hr-officer` and then `hr-manager`: hire a person end to end; find them by old-format NIC; change their grade from a past date; approve it as the manager; upload a document; change the bank account; transfer them to the other company; open the old and new profiles; exit the new one.
- Every screen works at 1280 px wide and follows `STYLES_GUIDE.md`.

## Do not

- Do not put validation that belongs to a service into a component. Components check only "required" and format; services own the rules.
- Do not show or accept an employee number in any form.
- Do not load all employees into memory for the list or the export — query with pagination / chunking.
- Do not add delete buttons.
