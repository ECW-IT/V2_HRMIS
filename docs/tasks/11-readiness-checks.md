# Task 11 — Readiness checks

**Depends on:** 10. **Read first:** `AGENTS.md`.

## Goal

A set of named checks that say, for any date, which active employees have data missing. In Phase 1 they drive a dashboard and the migration sign-off. In Phase 3 the same checks will stop a pay run. This is the direct answer to "payroll fails because of data errors".

## Files

- `Modules/CoreHr/app/Readiness/`: `ReadinessCheck.php` (interface), `Severity.php` (enum), `ReadinessIssue.php` (readonly value object), `ReadinessService.php`, `Checks/Hr001NoAssignment.php` … `Checks/Hr011NoMobile.php`.
- `Modules/CoreHr/config/readiness.php` — the ordered list of check classes.
- `Modules/CoreHr/app/Livewire/ReadinessDashboard.php` + view; route `employees.readiness`.
- Modify: dashboard home to show the readiness tile.
- Tests: `Modules/CoreHr/tests/Feature/ReadinessChecksTest.php`, `ReadinessDashboardTest.php`.

## Interfaces (must match exactly)

```php
namespace Modules\CoreHr\Readiness;

enum Severity: string { case BLOCKER = 'BLOCKER'; case WARNING = 'WARNING'; }

interface ReadinessCheck
{
    public function code(): string;                 // 'HR003'
    public function title(): string;                // 'EPF number missing'
    public function severity(): Severity;
    /** Ids of ACTIVE or SUSPENDED employments of $company, hired on or before $asOf, that fail this check on $asOf. */
    public function failing(Company $company, \Carbon\CarbonImmutable $asOf): array;
}

final readonly class ReadinessIssue
{
    public function __construct(public string $code, public string $title, public Severity $severity, public int $employmentId) {}
}

final class ReadinessService
{
    /** @return \Illuminate\Support\Collection<int, ReadinessIssue> */
    public function run(Company $company, \DateTimeInterface|string $asOf): Collection;
    public function forEmployment(Employment $e, \DateTimeInterface|string $asOf): Collection;
    /** [code => ['title' =>, 'severity' =>, 'count' =>]] for every configured check, including zero counts. */
    public function summary(Company $company, \DateTimeInterface|string $asOf): array;
}
```

Each check runs as **one set-based query** per company (no per-employee loops), so 300 employees × 11 checks stays fast.

## The checks

Population for every check: employments of the company with status `ACTIVE` or `SUSPENDED` and `hire_date <= asOf`. `EXITED` employments are never reported.

| Code | Severity | Title | Fails when |
|---|---|---|---|
| `HR001` | BLOCKER | No assignment | no assignment effective on `asOf` |
| `HR002` | BLOCKER | Assignment points to an inactive record | the assignment effective on `asOf` has an inactive org unit, job, grade or location |
| `HR003` | BLOCKER | EPF number missing | `epf_liable` and `epf_no` is null or empty |
| `HR004` | BLOCKER | No bank account | no bank account effective on `asOf` (a cash record counts as present) |
| `HR005` | BLOCKER | No fingerprint enrolment | no enrolment valid on `asOf` |
| `HR006` | BLOCKER | Manager has left | assignment's manager is an `EXITED` employment |
| `HR007` | WARNING | NIC does not match date of birth or gender | `persons.nic_mismatch` is true |
| `HR008` | WARNING | Probation review overdue | type `PROBATION` and `confirmation_due_date < asOf` |
| `HR009` | WARNING | Mandatory document missing | for any active mandatory document type, the person has no non-archived document of that type |
| `HR010` | WARNING | Document expired or expiring | a non-archived document has `expires_on <= asOf + 30 days` |
| `HR011` | WARNING | No mobile number | the person has no `MOBILE` contact |

Interns: `HR003` never fails (they are not EPF-liable). No check requires a basic salary — salary arrives in Phase 3.

## Screen — Readiness dashboard (`employees.readiness`, permission `employee.view`)

- Uses the selected company; with "All companies" selected, shows one section per permitted company.
- "As of" date picker, default today.
- Two cards: **Blockers** (total count, red when > 0) and **Warnings** (amber).
- Table: code, title, severity, count. Rows with count 0 show a tick. Clicking a row lists the employees failing that check (employee no., name, department, job) with a link to the profile (route exists from Task 13; until then render the number as plain text).
- Export the selected check's employee list to Excel.
- Home dashboard tile: "Payroll readiness — N blockers, M warnings" linking here.

## Tests

`ReadinessChecksTest.php` — uses `HrFixtures`. A helper `ready(Company $c, string $hire = '2026-01-01', array $emp = []): Employment` creates an employee who **passes every check**: hired, bank account from hire date, enrolment (`'NIT'`, unique id) from hire date, a `MOBILE` contact, and a document for every mandatory type. The test `setUp` runs `DocumentTypeSeeder` and uses `Storage::fake('private')`. `$this->codes($e, $asOf = '2026-11-01')` returns the sorted list of codes from `forEmployment`.

```php
public function test_fully_set_up_employee_has_no_issues(): void
{
    $this->assertSame([], $this->codes($this->ready($this->company())));
}

public function test_hr001_fails_when_there_is_no_assignment_on_the_date(): void
{
    $e = $this->ready($this->company(), '2026-06-01');
    Assignment::where('employment_id', $e->id)->update(['effective_from' => '2026-12-01']);   // corrupt on purpose
    $this->assertContains('HR001', $this->codes($e));
}

public function test_hr002_fails_when_the_grade_was_deactivated(): void
{
    $e = $this->ready($this->company());
    Grade::whereKey($e->currentAssignment()->grade_id)->update(['is_active' => false]);
    $this->assertSame(['HR002'], $this->codes($e));
}

public function test_hr003_fails_without_epf_number_but_ignores_interns(): void
{
    $c = $this->company();
    $liable = $this->ready($c);
    DB::table('employments')->where('id', $liable->id)->update(['epf_no' => '']);   // '' passes the DB check constraint but is not a usable number
    $intern = $this->ready($c, emp: ['type' => 'INTERN']);

    $this->assertSame(['HR003'], $this->codes($liable));
    $this->assertSame([], $this->codes($intern));
}

public function test_hr004_fails_without_bank_account_and_passes_for_cash(): void
{
    $c = $this->company();
    $none = $this->ready($c); EmployeeBankAccount::where('employment_id', $none->id)->delete();
    $cash = $this->ready($c); EmployeeBankAccount::where('employment_id', $cash->id)->delete();
    app(BankAccountService::class)->set($cash, ['effective_from' => '2026-01-01', 'is_cash' => true]);

    $this->assertSame(['HR004'], $this->codes($none));
    $this->assertSame([], $this->codes($cash));
}

public function test_hr004_looks_at_the_date_asked_for(): void
{
    $e = $this->ready($this->company(), '2026-01-01');
    EmployeeBankAccount::where('employment_id', $e->id)->update(['effective_from' => '2026-06-01']);
    $this->assertContains('HR004', $this->codes($e, '2026-05-31'));
    $this->assertNotContains('HR004', $this->codes($e, '2026-06-01'));
}

public function test_hr005_fails_when_the_enrolment_ended_before_the_date(): void
{
    $e = $this->ready($this->company());
    BiometricEnrolment::where('employment_id', $e->id)->update(['valid_to' => '2026-10-31']);
    $this->assertSame(['HR005'], $this->codes($e, '2026-11-01'));
    $this->assertSame([], $this->codes($e, '2026-10-31'));
}

public function test_hr006_fails_when_the_manager_has_exited(): void
{
    $c = $this->company(); $boss = $this->ready($c); $e = $this->ready($c);
    app(EmploymentService::class)->changeAssignment($e, '2026-02-01', ['manager_employment_id' => $boss->id], 'Reporting line');
    app(EmploymentService::class)->exit($boss, '2026-09-30', ExitReason::RESIGNATION);
    $this->assertSame(['HR006'], $this->codes($e));
}

public function test_hr007_reports_the_import_mismatch_flag(): void
{
    $e = $this->ready($this->company()); $e->person->update(['nic_mismatch' => true]);
    $this->assertSame(['HR007'], $this->codes($e));
}

public function test_hr008_fails_only_after_the_due_date(): void
{
    $e = $this->ready($this->company(), '2026-03-01', ['type' => 'PROBATION']);      // due 2026-09-01
    $this->assertNotContains('HR008', $this->codes($e, '2026-09-01'));
    $this->assertContains('HR008', $this->codes($e, '2026-09-02'));
}

public function test_hr009_fails_when_a_mandatory_document_is_missing_or_archived(): void
{
    $e = $this->ready($this->company());
    $doc = $e->person->documents()->first();
    app(DocumentService::class)->archive($doc);
    $this->assertSame(['HR009'], $this->codes($e));
}

public function test_hr010_fails_inside_thirty_days_of_expiry(): void
{
    $e = $this->ready($this->company());
    $this->addDocument($e->person, 'DRIVING_LICENCE', expiresOn: '2026-12-01');
    $this->assertNotContains('HR010', $this->codes($e, '2026-10-31'));     // 31 days before
    $this->assertContains('HR010', $this->codes($e, '2026-11-01'));        // 30 days before
    $this->assertContains('HR010', $this->codes($e, '2026-12-15'));        // expired
}

public function test_hr011_fails_without_a_mobile_contact(): void
{
    $e = $this->ready($this->company()); $e->person->contacts()->delete();
    $this->assertSame(['HR011'], $this->codes($e));
}

public function test_exited_employees_are_never_reported(): void
{
    $c = $this->company(); $e = $this->ready($c);
    EmployeeBankAccount::where('employment_id', $e->id)->delete();
    app(EmploymentService::class)->exit($e, '2026-10-31', ExitReason::RESIGNATION);
    $this->assertCount(0, app(ReadinessService::class)->run($c, '2026-11-01'));
}

public function test_employee_not_yet_hired_on_the_date_is_not_reported(): void
{
    $c = $this->company(); $e = $this->hired('2026-12-01', $c);      // nothing set up
    $this->assertCount(0, app(ReadinessService::class)->run($c, '2026-11-01'));
}

public function test_run_is_limited_to_the_company(): void
{
    $a = $this->company(); $b = $this->company();
    $this->hired('2026-01-01', $a); $this->hired('2026-01-01', $b);
    $ids = app(ReadinessService::class)->run($a, '2026-11-01')->pluck('employmentId')->unique();
    $this->assertCount(1, $ids);
}

public function test_summary_lists_every_check_including_zero_counts(): void
{
    $c = $this->company(); $this->ready($c);
    $summary = app(ReadinessService::class)->summary($c, '2026-11-01');
    $this->assertSame(['HR001','HR002','HR003','HR004','HR005','HR006','HR007','HR008','HR009','HR010','HR011'], array_keys($summary));
    $this->assertSame(0, array_sum(array_column($summary, 'count')));
}

public function test_checks_do_not_query_per_employee(): void
{
    $c = $this->company(); foreach (range(1, 20) as $i) { $this->ready($c); }
    DB::enableQueryLog();
    app(ReadinessService::class)->run($c, '2026-11-01');
    $this->assertLessThanOrEqual(25, count(DB::getQueryLog()));
}
```

`ReadinessDashboardTest.php`

```php
public function test_dashboard_needs_employee_view(): void                 // 403 without, 200 with
public function test_dashboard_shows_counts_for_the_selected_company(): void
// company with one employee missing a bank account -> sees 'HR004' row with count 1 and Blockers card '1'
public function test_clicking_a_check_lists_the_failing_employees(): void  // Livewire ->call('show', 'HR004') ->assertSee($employee_no)
```

## Done when

All tests pass; suite and Pint clean; by hand, the dashboard shows blockers for a freshly hired employee and clears as bank account, enrolment and documents are added.

## Do not

- Do not add salary or leave checks — later phases register their own check classes in the config list.
- Do not auto-fix anything. Checks only report.
