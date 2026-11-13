# Task 14 — Legacy import

**Depends on:** 09, 10, and the outputs of **Task 00**. **Read first:** `AGENTS.md`; `docs/hrmis_v2/04_decisions.md` D8; `docs/hrmis_v2/01_current_state_assessment.md` sections 2.1, 2.2, 3.2.

Work through the Steps one at a time and pause after each.

## Goal

Load the **currently active employees** and their present state from the old HRM and payroll databases. No history. The import is a repeatable program: run it as often as needed against fresh copies of the old databases; never fix the result by hand.

It loads through the **same services** as the screens, so every rule from Tasks 08–10 applies to migrated data too.

## Before the agent starts (human)

- The two old databases are restored locally and reachable as connections `legacy_hrm` and `legacy_payroll` with a **read-only** MySQL user.
- Two empty throw-away schemas exist for the tests: `ecw_hrmis_test_lhrm` and `ecw_hrmis_test_lpay`. The test fixtures create and fill their own tables there. This is the **only** place anything is written on a `legacy_*` connection, and only under the `testing` environment.
- The mapping files from Task 00 exist in `docs/mapping/`. If they do not, the agent builds and tests the import with the fixture rows described under Tests and stops before any real run.

## Files

- Module `Modules/LegacyImport`: `app/Console/{StageCommand, ValidateCommand, LoadCommand, ReportCommand}.php`; `app/Services/{Stager, Validator, Loader, Reconciler, MappingRepository}.php`; `app/Support/ImportIssue.php`; models `StgEmployee`, `StgIssue`, `ImportRun`; migrations; `config/legacy_import.php`.
- Tests: `Modules/LegacyImport/tests/Feature/{StageTest, ValidateTest, LoadTest, ReconcileTest}.php`; `Modules/LegacyImport/tests/Support/LegacyFixtures.php`.

## Legacy source tables used

From `legacy_hrm` (database `hrmeclk_db`):

| Table | Columns used |
|---|---|
| `employees` | `id`, `emp_id` (employee number text), `emp_fp_id`, `emp_etfno`, `emp_name_with_initial`, `calling_name`, `emp_first_name`, `emp_med_name`, `emp_last_name`, `emp_status` (1 Permanent, 2 Probation, 3 Intern), `emp_birthday`, `emp_gender` (`Male`/`Female`/empty), `emp_marital_status`, `emp_join_date`, `emp_permanent_date`, `emp_national_id`, `emp_mobile`, `emp_email`, `emp_address`, `emp_address_2`, `emp_city`, `emp_department`, `emp_branch`, `job_title_id`, `is_resigned`, `deleted` |
| `section_employees` | `employee_id`, `section_id`, `status` (`active`) |
| `emergency_contacts` | `emp_id` (= `employees.id`), `name`, `relationship`, `address`, `contact_no`, `status` |
| `employee_attachments` | `emp_id`, `emp_ath_file_name`, `attachment_type`, `status` |

From `legacy_payroll` (database `pyrecwl_db`): `payr__employee_profile` — `emp_no`, `epf_no`, `bank_account`, `status` (1 active), `branch_id`.

Active employee in HRM = `is_resigned = 0 AND deleted = 0`.

## Schema

**`import_runs`** — `id`, `started_at`, `finished_at` null, `stage` string(10) (`STAGED`, `VALIDATED`, `LOADED`), `summary` json null, `run_by` FK users null.

**`stg_employees`** — `id`, `import_run_id` FK, `legacy_hrm_id` unsigned int, `legacy_branch_id` int null, `raw` json (the legacy row plus `section_id`, `payroll` {`emp_no`,`epf_no`,`bank_account`,`status`} or null, `emergency_contacts` [], `attachments` []), `normalised_employee_no` string(30) null, `normalised_nic` string(12) null, `company_code` string(10) null, `loaded_employment_id` unsigned bigint null, `load_error` text null.

**`stg_issues`** — `id`, `import_run_id` FK, `legacy_hrm_id` unsigned int null, `code` string(10), `severity` string(7) (`ERROR`/`WARNING`), `message` string(500).

## Commands

| Command | Behaviour |
|---|---|
| `hrmis:import:stage` | Creates a new `import_runs` row; fills `stg_employees` for every active HRM employee; matches the payroll profile by `EmployeeNumberGenerator::normalise(emp_no)` **and** company (through `company_map.csv` on `branch_id`). Prints counts. |
| `hrmis:import:validate {--run=}` | Latest run by default. Clears and rewrites `stg_issues`. Prints a table of issue counts by code. Exit code 1 when any `ERROR`. |
| `hrmis:import:load {--run=} {--dry-run}` | Refuses (exit 1) when the run has `ERROR` issues, or when the target database already contains any employment (message: "Load needs an empty employee master. Run migrate:fresh --seed first."). Loads one employee per transaction; a failure records `load_error` and continues. `--dry-run` rolls every transaction back and still reports. After a real load, sets each company's `next_employee_seq` to (highest migrated sequence + 1). |
| `hrmis:import:report {--run=}` | Prints the reconciliation and writes it to `storage/app/private/import/reconciliation-{run}.xlsx`. |

All four need the `import.run` permission when started from a screen; from the console they run as system (no user).

## Mapping files (`docs/mapping/`, read by `MappingRepository`)

`company_map.csv`, `org_unit_map.csv`, `job_map.csv`, `grade_assignment.csv`, `device_groups.csv`, `document_type_map.csv`, `org_units.csv`, `jobs.csv` — formats in Task 00. `MappingRepository` fails with a clear message naming the file and line when a file is missing, has the wrong header or a blank key. The paths come from `config/legacy_import.php` so tests can point to fixture files.

Before staging, `hrmis:import:stage` creates the org units and jobs listed in `org_units.csv` and `jobs.csv` if they do not exist (through `OrgUnitService` / `JobService`).

## Validation rules

| Code | Severity | Raised when |
|---|---|---|
| `IMP001` | ERROR | two or more staged employees share the same normalised NIC |
| `IMP002` | WARNING | date of birth or gender disagrees with the NIC (the NIC-derived values will be used and `nic_mismatch` set) |
| `IMP003` | ERROR | `emp_branch` has no row in `company_map.csv` |
| `IMP004` | ERROR | two staged employees of the same company have the same normalised employee number |
| `IMP005` | ERROR | `emp_join_date` is empty |
| `IMP006` | ERROR | `emp_status` is not 1, 2 or 3 |
| `IMP007` | WARNING | no usable EPF number and not an intern (loaded as not EPF-liable; shows up for HR to fix) |
| `IMP008` | ERROR | HRM and payroll EPF numbers are both usable and differ |
| `IMP009` | ERROR | no `org_unit_map.csv` row for the department (+ section) |
| `IMP010` | ERROR | no `job_map.csv` row for `job_title_id` |
| `IMP011` | ERROR | no `grade_assignment.csv` row for the employee, or an unknown grade code |
| `IMP012` | WARNING | `emp_fp_id` is 0 or empty (no enrolment created) |
| `IMP013` | ERROR | two staged employees in the same device group share `emp_fp_id` |
| `IMP014` | WARNING | an attachment's file is not found on disk |
| `IMP015` | ERROR | active in HRM with no **active** payroll profile in the same company |
| `IMP016` | ERROR | NIC empty or not a valid NIC |
| `IMP017` | ERROR | an active payroll profile has no active HRM employee (reported with `legacy_hrm_id` null and the payroll `emp_no` in the message) |
| `IMP018` | WARNING | no mobile number |

"Usable EPF number": after trimming, not empty, not `.`, not `-`, not `0`.

## Field mapping (Loader)

| Target | Source and rule |
|---|---|
| `persons.nic` | `emp_national_id` → `Nic::normalise` |
| `first_name` / `middle_name` / `last_name` | `emp_first_name` / `emp_med_name` / `emp_last_name`; when `emp_last_name` is empty use the last word of `emp_name_with_initial` |
| `name_with_initials`, `calling_name` | `emp_name_with_initial`, `calling_name` |
| `date_of_birth`, `gender` | from the legacy row when they agree with the NIC; otherwise from the NIC, with `nic_mismatch = true`. The import calls `PersonService::create` with the NIC-derived values, then sets the flag. |
| `marital_status` | `Married`/`Single`… → enum, anything else null |
| contacts | `emp_mobile` → `MOBILE` primary (digits only; skip if fewer than 9 digits); `emp_email` → `EMAIL` when it is a valid address |
| address | `PERMANENT`: `line1` = `emp_address`, `line2` = `emp_address_2`, `city` = `emp_city` or `'-'`; skipped when `emp_address` is empty |
| emergency contacts | rows of `emergency_contacts` with `status = 1` |
| `employments.company_id` | `company_map.csv[emp_branch]` |
| `employee_no` | `EmployeeNumberGenerator::normalise(emp_id)`, passed to `hire()` as `employee_no` |
| `legacy_employee_no` | `emp_id` unchanged |
| `type` | 1 → `PERMANENT`, 2 → `PROBATION`, 3 → `INTERN` |
| `hire_date` | `emp_join_date` |
| `confirmed_on` | `emp_permanent_date` when type is `PERMANENT` |
| `epf_no`, `epf_liable` | usable HRM value, else usable payroll value; liable = has a usable number and is not an intern |
| assignment | `org_unit_map` (section-specific row first, then the department row), `job_map`, `grade_assignment`; `location_id` from the `location_code` column of `company_map.csv`; `effective_from` = hire date; no manager |
| bank account | payroll `bank_account`: digits only; 6–20 digits → a **non-cash** account cannot be created without bank and branch, so store nothing and leave `HR004` to flag it; **write the account number to the reconciliation file** (sheet "Bank accounts to complete": employee no., name, account no.) for HR to enter with bank and branch |
| enrolment | `emp_fp_id` > 0 → `EnrolmentService::enrol(employment, device_groups.csv[emp_branch], emp_fp_id, hire_date)` |
| documents | each attachment with `status = 1` whose file exists under `config('legacy_import.attachment_path')` → `DocumentService::upload` with the mapped type (`OTHER` when unmapped) |

Because of rule `IMP001`, every staged employee becomes one new person and one new employment; the loader does not need to merge persons.

## Reconciliation report

Per company and in total: legacy active count; staged; loaded; failed to load (with reason); counts by employment type and by grade; EPF-liable count; issues by code; readiness blocker counts after load (from `ReadinessService::summary`). Sheets: Summary, Issues, Load failures, Bank accounts to complete.

**Balancing rule (must hold):** for each company, `staged = loaded + failed`, and `sum(staged) = legacy active count`.

## Steps

- [ ] 1. Migrations, `MappingRepository`, `LegacyFixtures`, `StageCommand`.
- [ ] 2. `Validator` and `ValidateCommand` — one rule at a time, test first.
- [ ] 3. `Loader` and `LoadCommand`.
- [ ] 4. `Reconciler` and `ReportCommand`.

## Tests

`LegacyFixtures` creates, on the `legacy_hrm` and `legacy_payroll` **test** connections (point both at throw-away schemas `ecw_hrmis_test_lhrm` / `ecw_hrmis_test_lpay` in `phpunit.xml`), minimal versions of the source tables with only the columns listed above, and offers:

```php
legacyEmployee(array $o = []): int       // inserts an active HRM employee with valid, unique values; returns its id
legacyProfile(array $o = []): void       // inserts a payroll profile
mappingFiles(array $overrides = []): void   // writes fixture CSVs to a temp dir and points config('legacy_import') at it
```

Defaults for `legacyEmployee`: branch 1, department 10, job title 20, status 1, join date `2024-03-01`, unique `emp_id` like `'ECW 0NN'`, unique valid NIC with matching birthday and gender, unique `emp_fp_id`, unique `emp_etfno`, a matching active payroll profile with the same EPF number. Default mapping files map branch 1 → `CO1` (location `NIT`, device group `NIT`), department 10 → org unit `PROD`, job title 20 → job `TECH`, and give every fixture employee grade `G3`.

`StageTest.php`

```php
public function test_only_active_employees_are_staged(): void
{
    $keep = $this->legacyEmployee();
    $this->legacyEmployee(['is_resigned' => 1]);
    $this->legacyEmployee(['deleted' => 1]);
    $this->artisan('hrmis:import:stage')->assertSuccessful();
    $this->assertSame([$keep], StgEmployee::pluck('legacy_hrm_id')->all());
}

public function test_employee_number_formats_normalise_to_one(): void
{
    $a = $this->legacyEmployee(['emp_id' => 'ECW 035']); $b = $this->legacyEmployee(['emp_id' => 'ecw_066']);
    $this->artisan('hrmis:import:stage');
    $this->assertSame('ECW035', StgEmployee::where('legacy_hrm_id', $a)->value('normalised_employee_no'));
    $this->assertSame('ECW066', StgEmployee::where('legacy_hrm_id', $b)->value('normalised_employee_no'));
}

public function test_payroll_profile_is_matched_despite_different_number_format(): void
{
    $id = $this->legacyEmployee(['emp_id' => 'ECW_066', 'payroll' => false]);
    $this->legacyProfile(['emp_no' => 'ECW 066', 'epf_no' => '321', 'branch_id' => 1, 'status' => 1]);
    $this->artisan('hrmis:import:stage');
    $this->assertSame('321', StgEmployee::where('legacy_hrm_id', $id)->sole()->raw['payroll']['epf_no']);
}

public function test_missing_mapping_file_stops_with_a_clear_message(): void
{
    $this->mappingFiles(['job_map.csv' => null]);
    $this->artisan('hrmis:import:stage')->expectsOutputToContain('job_map.csv')->assertFailed();
}

public function test_staging_never_writes_to_the_legacy_databases(): void
{
    $this->legacyEmployee();
    DB::connection('legacy_hrm')->enableQueryLog(); DB::connection('legacy_payroll')->enableQueryLog();
    $this->artisan('hrmis:import:stage');
    foreach (['legacy_hrm', 'legacy_payroll'] as $c) {
        foreach (DB::connection($c)->getQueryLog() as $q) { $this->assertStringStartsWith('select', strtolower(ltrim($q['query']))); }
    }
}
```

`ValidateTest.php` — helper `codes(?int $legacyId = null): array` runs stage + validate and returns sorted distinct issue codes (for that employee, or all).

```php
public function test_clean_employee_has_no_issues(): void
{
    $id = $this->legacyEmployee();
    $this->assertSame([], $this->codes($id));
    $this->artisan('hrmis:import:validate')->assertSuccessful();
}

public function test_imp001_two_active_rows_sharing_a_nic(): void
{
    $a = $this->legacyEmployee(['emp_national_id' => '853400937V', 'emp_birthday' => '1985-12-05', 'emp_gender' => 'Male']);
    $b = $this->legacyEmployee(['emp_national_id' => '198534000937', 'emp_birthday' => '1985-12-05', 'emp_gender' => 'Male']);
    $this->assertContains('IMP001', $this->codes($a)); $this->assertContains('IMP001', $this->codes($b));
    $this->artisan('hrmis:import:validate')->assertFailed();
}

public function test_imp002_birthday_disagreeing_with_nic_is_a_warning(): void
{
    $id = $this->legacyEmployee(['emp_national_id' => '853400937V', 'emp_birthday' => '1985-12-06', 'emp_gender' => 'Male']);
    $this->assertSame(['IMP002'], $this->codes($id));
    $this->artisan('hrmis:import:validate')->assertSuccessful();          // warnings do not fail
}

public function test_imp004_same_number_in_one_company(): void
{
    $a = $this->legacyEmployee(['emp_id' => 'ECW 035']); $b = $this->legacyEmployee(['emp_id' => 'ECW035']);
    $this->assertContains('IMP004', $this->codes($a)); $this->assertContains('IMP004', $this->codes($b));
}

public function test_same_number_in_two_companies_is_not_an_issue(): void
{
    $this->mappingFiles(['company_map.csv' => "legacy_branch_id,company_code,location_code\n1,CO1,NIT\n2,CO2,NEG\n"]);
    $a = $this->legacyEmployee(['emp_id' => 'ECW 003', 'emp_branch' => 1]); $b = $this->legacyEmployee(['emp_id' => 'ECW 003', 'emp_branch' => 2]);
    $this->assertNotContains('IMP004', $this->codes($a)); $this->assertNotContains('IMP004', $this->codes($b));
}

public function test_placeholder_epf_values_count_as_missing(): void
{
    foreach (['.', '0', '', '-', null] as $v) {
        $id = $this->legacyEmployee(['emp_etfno' => $v, 'payroll_epf' => $v]);
        $this->assertContains('IMP007', $this->codes($id), var_export($v, true));
    }
}

public function test_intern_without_epf_is_not_an_issue(): void
{
    $id = $this->legacyEmployee(['emp_status' => 3, 'emp_etfno' => null, 'payroll_epf' => null]);
    $this->assertNotContains('IMP007', $this->codes($id));
}

public function test_imp008_hrm_and_payroll_epf_differ(): void
{
    $id = $this->legacyEmployee(['emp_etfno' => '500', 'payroll_epf' => '501']);
    $this->assertContains('IMP008', $this->codes($id));
}

public function test_imp013_shared_fingerprint_id_in_one_device_group(): void
{
    $a = $this->legacyEmployee(['emp_fp_id' => 116]); $b = $this->legacyEmployee(['emp_fp_id' => 116]);
    $this->assertContains('IMP013', $this->codes($a)); $this->assertContains('IMP013', $this->codes($b));
}

public function test_imp012_fingerprint_id_zero_is_a_warning(): void          // emp_fp_id 0 -> ['IMP012']
public function test_imp003_unmapped_branch(): void                           // emp_branch 9 -> contains IMP003
public function test_imp005_missing_join_date(): void                         // emp_join_date null -> contains IMP005
public function test_imp006_unknown_employment_status(): void                 // emp_status 7 -> contains IMP006
public function test_imp009_unmapped_department(): void                       // emp_department 99 -> contains IMP009
public function test_imp010_unmapped_job_title(): void                        // job_title_id 99 -> contains IMP010
public function test_imp011_missing_or_unknown_grade(): void                  // no row -> IMP011; row with grade 'ZZ' -> IMP011
public function test_imp015_no_active_payroll_profile(): void                 // payroll => false -> IMP015; payroll status 2 -> IMP015
public function test_imp016_invalid_or_empty_nic(): void                      // '' and '12345' -> IMP016
public function test_imp017_active_payroll_profile_without_hrm_employee(): void
// legacyProfile(['emp_no' => 'ECW 900', 'status' => 1]) only -> an issue IMP017 with legacy_hrm_id null and 'ECW 900' in the message
public function test_section_specific_mapping_wins_over_department_mapping(): void
// org_unit_map has (10, blank -> PROD) and (10, 55 -> PAINT); employee in dept 10 section 55 -> staged org unit PAINT, no IMP009
public function test_validate_rewrites_issues_each_time(): void               // run twice -> issue count unchanged
```

`LoadTest.php` — `setUp` seeds companies, grades, locations, document types.

```php
public function test_load_refuses_while_errors_exist(): void
{
    $this->legacyEmployee(['emp_join_date' => null]);
    $this->artisan('hrmis:import:stage'); $this->artisan('hrmis:import:validate');
    $this->artisan('hrmis:import:load')->expectsOutputToContain('ERROR')->assertFailed();
    $this->assertSame(0, Employment::count());
}

public function test_clean_employee_is_loaded_through_the_services(): void
{
    $id = $this->legacyEmployee(['emp_id' => 'ECW 035', 'emp_national_id' => '853400937V', 'emp_birthday' => '1985-12-05', 'emp_gender' => 'Male',
        'emp_join_date' => '2024-03-01', 'emp_status' => 1, 'emp_etfno' => '500', 'emp_fp_id' => 116, 'emp_mobile' => '077-123 4567']);
    $this->runImport();

    $e = Employment::withoutGlobalScopes()->sole();
    $this->assertSame('ECW035', $e->employee_no);
    $this->assertSame('ECW 035', $e->legacy_employee_no);
    $this->assertSame('CO1', $e->company->code);
    $this->assertSame('198534000937', $e->person->nic);
    $this->assertSame('2024-03-01', $e->hire_date->toDateString());
    $this->assertSame(EmploymentType::PERMANENT, $e->type);
    $this->assertTrue($e->epf_liable); $this->assertSame('500', $e->epf_no);
    $a = $e->assignments()->sole();
    $this->assertSame('2024-03-01', $a->effective_from->toDateString());
    $this->assertSame('PROD', $a->orgUnit->code); $this->assertSame('TECH', $a->job->code); $this->assertSame('G3', $a->grade->code);
    $this->assertSame($e->id, app(EnrolmentService::class)->resolve('NIT', 116, '2026-12-01 08:00:00'));
    $this->assertSame('0771234567', $e->person->contacts()->where('type', 'MOBILE')->sole()->value);
    $this->assertSame(EventType::HIRE, $e->events()->sole()->type);
    $this->assertSame($e->id, StgEmployee::where('legacy_hrm_id', $id)->value('loaded_employment_id'));
}

public function test_nic_mismatch_uses_nic_values_and_sets_the_flag(): void
{
    $this->legacyEmployee(['emp_national_id' => '853400937V', 'emp_birthday' => '1985-12-06', 'emp_gender' => 'Female']);
    $this->runImport();
    $p = Person::sole();
    $this->assertSame('1985-12-05', $p->date_of_birth->toDateString());
    $this->assertSame(Gender::MALE, $p->gender);
    $this->assertTrue($p->nic_mismatch);
}

public function test_employee_without_usable_epf_is_loaded_as_not_liable(): void     // emp_etfno '.' -> epf_liable false, epf_no null
public function test_intern_is_loaded_as_intern_and_not_liable(): void
public function test_fingerprint_id_zero_creates_no_enrolment(): void

public function test_next_employee_seq_is_set_above_the_highest_migrated_number(): void
{
    $this->legacyEmployee(['emp_id' => 'ECW 035']); $this->legacyEmployee(['emp_id' => 'ECW 412']);
    $this->runImport();
    $this->assertSame(413, Company::where('code', 'CO1')->value('next_employee_seq'));
}

public function test_load_refuses_when_employees_already_exist(): void
{
    $this->legacyEmployee(); $this->runImport();
    $this->artisan('hrmis:import:load')->expectsOutputToContain('empty employee master')->assertFailed();
    $this->assertSame(1, Employment::withoutGlobalScopes()->count());
}

public function test_dry_run_saves_nothing(): void
{
    $this->legacyEmployee();
    $this->artisan('hrmis:import:stage'); $this->artisan('hrmis:import:validate');
    $this->artisan('hrmis:import:load --dry-run')->assertSuccessful();
    $this->assertSame(0, Employment::withoutGlobalScopes()->count()); $this->assertSame(0, Person::count());
}

public function test_one_failing_employee_does_not_stop_the_others(): void
{
    $good = $this->legacyEmployee(); $bad = $this->legacyEmployee();
    $this->artisan('hrmis:import:stage'); $this->artisan('hrmis:import:validate');
    StgEmployee::where('legacy_hrm_id', $bad)->update(['normalised_nic' => 'BROKEN']);      // force a load failure after validation
    $this->artisan('hrmis:import:load')->assertSuccessful();
    $this->assertSame(1, Employment::withoutGlobalScopes()->count());
    $this->assertNotNull(StgEmployee::where('legacy_hrm_id', $bad)->value('load_error'));
    $this->assertNull(StgEmployee::where('legacy_hrm_id', $bad)->value('loaded_employment_id'));
}

public function test_import_counts_match_the_legacy_rows(): void
{
    foreach (range(1, 5) as $i) { $this->legacyEmployee(); }
    $this->runImport();
    $this->assertSame([5, 5, 5, 5], [Person::count(), Employment::withoutGlobalScopes()->count(), Assignment::count(), BiometricEnrolment::count()]);
    $this->assertSame(5, StgEmployee::whereNotNull('loaded_employment_id')->count());
}
```

(`runImport()` = stage, validate, load, each asserted successful.)

`ReconcileTest.php`

```php
public function test_totals_balance_per_company(): void
// 3 employees in CO1 (one forced to fail at load), 2 in CO2 -> summary: CO1 staged 3, loaded 2, failed 1; CO2 staged 2, loaded 2, failed 0; total staged 5 = legacy active 5
public function test_report_file_has_the_four_sheets(): void
public function test_bank_accounts_to_complete_sheet_lists_payroll_account_numbers(): void
// payroll bank_account '0012345678' -> row with employee no., name and '0012345678'; no employee_bank_accounts row was created
public function test_report_includes_readiness_blockers_after_load(): void    // HR004 count equals loaded employees (no bank accounts yet)
```

## Running it for real (human, after the tests pass)

1. Restore fresh dumps of both old databases.
2. `php artisan migrate:fresh --seed` (with the **real** Task 00 seed files).
3. `php artisan hrmis:import:stage` → `validate`. Fix every `ERROR` **in the old systems or the mapping files**, restore/stage again. Repeat until zero errors.
4. `php artisan hrmis:import:load --dry-run`, then `load`, then `report`.
5. HR completes bank and branch from the "Bank accounts to complete" sheet and checks 30 records against paper files.

## Done when

All tests pass; suite and Pint clean; a full run on fresh dumps gives zero `ERROR` issues and a balancing reconciliation for both companies; running the whole sequence a second time from `migrate:fresh --seed` gives the same counts.

## Do not

- Do not import resigned or deleted employees, punches, OT, leave, paysheets, audit logs or users.
- Do not write to `legacy_hrm` or `legacy_payroll`.
- Do not "fix" bad legacy values inside the loader beyond the rules above. Report them.
- Do not bypass the services with direct inserts into `persons`, `employments` or `assignments`.
- Do not guess a mapping. A missing mapping is an `ERROR`.
