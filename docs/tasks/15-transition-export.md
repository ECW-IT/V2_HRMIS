# Task 15 — Transition export

**Depends on:** 09, 10. **Read first:** `AGENTS.md`.

## Goal

After Phase 1, HRMIS v2 is where employees are hired, changed and exited — but the old HRM and payroll still run attendance and pay until Phases 2 and 3. This task produces a daily list of what changed, so HR can apply it in the old systems by hand.

It **never writes** to the old databases.

## Files

- `Modules/LegacyImport/app/Console/ExportLegacySyncCommand.php`; `app/Services/LegacySyncExporter.php`; `app/Exports/LegacySyncExport.php` (three sheets); model `LegacySyncRun`; migration; Livewire `LegacySyncIndex` + view; route.
- Modify: `routes/console.php` — schedule daily at 18:00.
- Test: `Modules/LegacyImport/tests/Feature/LegacySyncExportTest.php`.

## Schema — `legacy_sync_runs`

`id`, `from_at` datetime, `to_at` datetime, `file_path` string(255), `new_hires` unsigned int, `exits` unsigned int, `changes` unsigned int, `applied_by` FK users null, `applied_at` datetime null, `created_at`.

## Behaviour

`php artisan hrmis:export:legacy-sync {--since=}`

- Window: from the previous run's `to_at` (or `--since`, or the start of today when there is no previous run) up to now. The window is on **`employment_events.created_at`** — when the change was recorded — not the event date, so back-dated changes are never missed.
- Covers all companies. Writes `storage/app/private/legacy-sync/legacy-sync-{Ymd-His}.xlsx` and a `legacy_sync_runs` row. When there is nothing to report, it still records the run (all counts 0) and writes no file (`file_path` empty).

**Sheets**

| Sheet | Rows from | Columns |
|---|---|---|
| New hires | events `HIRE`, `REJOIN`, `SISTER_COMPANY_TRANSFER_IN` | Company, Employee no., Old employee no. (for transfer-in and rejoin: the previous employment's number), Name with initials, Calling name, NIC, Hire date, Type, Department, Section, Job, Grade, EPF no., Bank, Branch, Account no., Fingerprint group, Fingerprint ID |
| Exits | events `EXIT`, `SISTER_COMPANY_TRANSFER_OUT` | Company, Employee no., Legacy employee no., Name with initials, Last working day, Reason, Remarks |
| Changes | events `ASSIGNMENT_CHANGE`, `CONFIRMATION`, `TYPE_CHANGE`, `SUSPENSION`, `REINSTATEMENT`, and bank-account changes (audit rows `CREATED` on `EmployeeBankAccount` for an employment that already had an account) | Company, Employee no., Legacy employee no., Name with initials, Change, Effective date, From, To |

- "From"/"To" for an assignment change list the changed fields by **name** ("Grade: G3 → G2").
- A sister-company transfer therefore appears twice: once in Exits (old company) and once in New hires (new company).
- The file contains bank account numbers, so the screen and download need **`employee.bank.view`** as well as `employee.view`.

**Screen — Legacy sync** (`legacy-sync.index`)

Table of runs, newest first: window, hires, exits, changes, Download, and "Mark as applied" (records who and when). Runs not yet applied are highlighted. A "Run now" button calls the exporter.

## Interfaces (must match exactly)

```php
namespace Modules\LegacyImport\Services;

final class LegacySyncExporter
{
    /** Builds the export for (from, to]. Returns the run. */
    public function run(?\DateTimeInterface $since = null): LegacySyncRun;
    /** ['hires' => Collection, 'exits' => Collection, 'changes' => Collection] of row arrays keyed by the column headings. */
    public function collect(\DateTimeInterface $from, \DateTimeInterface $to): array;
}
```

## Tests — `LegacySyncExportTest.php` (uses `HrFixtures`; `Storage::fake('private')`; `Excel::fake()` where the file content is not inspected)

```php
public function test_export_lists_hires_exits_and_changes_in_the_window(): void
{
    $c = $this->company();
    Carbon::setTestNow('2026-11-30 12:00:00');
    $before = $this->hired('2026-01-01', $c);                        // recorded before the window
    Carbon::setTestNow('2026-12-01 09:00:00');
    $new = $this->hired('2026-12-01', $c);
    $g2 = Grade::factory()->create(['code' => 'G2', 'name' => 'Grade Two']);
    app(EmploymentService::class)->changeAssignment($before, '2026-12-01', ['grade_id' => $g2->id], 'Promotion');
    $leaver = $this->hired('2026-06-01', $c);
    app(EmploymentService::class)->exit($leaver, '2026-12-01', ExitReason::RESIGNATION, 'Moving abroad');

    $rows = app(LegacySyncExporter::class)->collect(Carbon::parse('2026-12-01 00:00:00'), Carbon::parse('2026-12-01 23:59:59'));

    $this->assertEqualsCanonicalizing([$new->employee_no, $leaver->employee_no], $rows['hires']->pluck('Employee no.')->all());
    $this->assertSame([$leaver->employee_no], $rows['exits']->pluck('Employee no.')->all());
    $this->assertSame('Moving abroad', $rows['exits'][0]['Remarks']);
    $change = $rows['changes']->sole();
    $this->assertSame($before->employee_no, $change['Employee no.']);
    $this->assertSame('2026-12-01', $change['Effective date']);
    $this->assertStringContainsString('Grade Two', $change['To']);
}

public function test_back_dated_change_is_caught_by_when_it_was_recorded(): void
{
    $e = $this->hired('2026-01-01');
    Carbon::setTestNow('2026-12-01 09:00:00');
    app(EmploymentService::class)->changeAssignment($e, '2026-03-01', ['grade_id' => Grade::factory()->create()->id], 'Late paperwork');

    $rows = app(LegacySyncExporter::class)->collect(Carbon::parse('2026-12-01 00:00:00'), Carbon::parse('2026-12-01 23:59:59'));
    $this->assertSame('2026-03-01', $rows['changes']->sole()['Effective date']);
}

public function test_second_run_does_not_repeat_rows(): void
{
    Carbon::setTestNow('2026-12-01 09:00:00'); $this->hired('2026-12-01');
    Carbon::setTestNow('2026-12-01 18:00:00'); $first = app(LegacySyncExporter::class)->run();
    Carbon::setTestNow('2026-12-02 18:00:00'); $second = app(LegacySyncExporter::class)->run();

    $this->assertSame(1, $first->new_hires);
    $this->assertSame(0, $second->new_hires);
    $this->assertSame($first->to_at->toDateTimeString(), $second->from_at->toDateTimeString());
    $this->assertSame('', $second->file_path);
}

public function test_sister_company_transfer_appears_as_an_exit_and_a_hire(): void
{
    $a = $this->company(); $b = $this->company();
    $old = $this->hired('2024-03-01', $a, ['epf_no' => '500']);
    Carbon::setTestNow('2026-12-01 09:00:00');
    $new = app(EmploymentService::class)->transferToSisterCompany($old, $b, '2026-11-30', $this->asg($b), ['epf_no' => '77']);

    $rows = app(LegacySyncExporter::class)->collect(Carbon::parse('2026-12-01 00:00:00'), Carbon::parse('2026-12-01 23:59:59'));

    $this->assertSame($old->employee_no, $rows['exits']->sole()['Employee no.']);
    $hire = $rows['hires']->sole();
    $this->assertSame($new->employee_no, $hire['Employee no.']);
    $this->assertSame($old->employee_no, $hire['Old employee no.']);
    $this->assertSame('77', $hire['EPF no.']);
}

public function test_bank_account_change_is_listed_but_first_account_is_not(): void
{
    $e = $this->hired('2026-01-01'); $svc = app(BankAccountService::class);
    Carbon::setTestNow('2026-11-30 12:00:00'); $svc->set($e, $this->account(['effective_from' => '2026-01-01']));
    Carbon::setTestNow('2026-12-01 09:00:00'); $svc->set($e, $this->account(['effective_from' => '2026-12-01', 'account_no' => '9998887776']));

    $rows = app(LegacySyncExporter::class)->collect(Carbon::parse('2026-12-01 00:00:00'), Carbon::parse('2026-12-01 23:59:59'));
    $change = $rows['changes']->sole();
    $this->assertSame('Bank account', $change['Change']);
    $this->assertStringContainsString('9998887776', $change['To']);
}

public function test_screen_and_download_need_bank_view(): void
// user with employee.view only -> 403 on legacy-sync.index; with employee.view + employee.bank.view -> 200
public function test_mark_as_applied_records_who_and_when(): void
public function test_run_with_nothing_to_report_writes_no_file(): void
```

`account()` is the helper from `HrFixtures` (Task 10).

## Done when

All tests pass; suite and Pint clean; by hand, hire one person and exit another, press "Run now", download the file and find both.

## Do not

- Do not connect to or write to the old HRM or payroll databases.
- Do not e-mail the file — it contains bank details.
- Do not remove this task's code later without checking Phases 2 and 3 have replaced the old systems.
