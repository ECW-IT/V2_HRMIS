# AGENTS.md — rules for AI agents working in `ecw_hrmis`

Copy this file to the root of the `ecw_hrmis` repository. It applies to every task.

## What this project is

HRMIS v2 for ECW: one Laravel application that is the employee master for two sister companies, and later runs attendance, leave and payroll. You are building it one task at a time from files in `docs/tasks/`.

## How to work

1. You will be given **one task file**. Do only that task. Do not start, scaffold or "prepare for" any other task.
2. Read the task file fully, then the files it lists under **Read first**.
3. For each test listed in the task: write the test exactly as specified, run it, confirm it **fails for the expected reason**, then write the minimum code to pass it.
4. When all the task's tests pass, run the whole suite and Pint (commands below). Both must be clean.
5. Stop and report. Do not commit unless asked.

**Stop and ask instead of guessing when:**

- a name, column or signature in the task conflicts with existing code;
- a test in the task cannot pass without changing the test;
- the task needs a table, class or package another task has not created yet;
- you would need to change a file outside the task's **Files** list;
- business data is missing (company list, grades, mappings). Never invent business data.

## Never do these

- Never edit, weaken, skip or delete a test to make it pass. If a specified test looks wrong, stop and say why.
- Never change a migration that is already committed. Add a new migration.
- Never put business logic in a Blade view, a Livewire `render()` method or JavaScript.
- Never query employee data with `withoutGlobalScopes()` outside `Modules/LegacyImport` and console commands.
- Never store dates, times or money as text or float.
- Never hard-delete business records (persons, employments, assignments, punches, audit logs).
- Never add a Composer or npm package the task does not name.
- Never create files named with dates or `_backup`, `_old`, `_copy`. Git is the history.
- Never touch `.env`, deployment files, CI secrets, or anything outside this repository.
- Never connect to, or write to, the `legacy_hrm` or `legacy_payroll` databases except to read in `Modules/LegacyImport`.

## Stack

PHP 8.3 · Laravel 13.8 · Livewire 4 · Tailwind 4 · `nwidart/laravel-modules` 13 · `spatie/laravel-permission` · PHPUnit 12 · Laravel Pint · MySQL 8 / MariaDB 10.6+.

Check the installed versions in `composer.lock` before using a framework feature. If a Livewire or Laravel API you want is not in the installed version, use what the existing components in this repository use.

## Commands

```bash
php artisan test                       # whole suite
php artisan test --filter=NameOfTest   # one test or class
./vendor/bin/pint --test               # style check
./vendor/bin/pint                      # fix style
php artisan migrate:fresh --seed       # rebuild local database
```

## Layout

```
app/Support/Company/     company scope
app/Support/Audit/       audit trail
app/Support/Effective/   effective dating
app/Exceptions/Domain/   DomainException base class
Modules/<Name>/
  app/Models  app/Services  app/Enums  app/Livewire  app/Policies  app/Exceptions
  database/migrations  database/factories
  resources/views  routes/web.php  routes/api.php
  tests/Feature  tests/Unit
tests/Support/           shared test helpers and probe models
docs/tasks/              the task files
docs/mapping/            CSVs supplied by HR
```

Namespaces follow nwidart defaults: `Modules\CoreHr\Models\Person`, `Modules\CoreHr\Services\PersonService`, `Modules\CoreHr\Tests\Feature\...`.

## Database rules

- Charset `utf8mb4`, collation `utf8mb4_unicode_ci`, InnoDB, strict mode.
- Schema changes only through migrations.
- Every reference between tables is a real foreign key (`constrained()`), with `restrictOnDelete()` unless the task says otherwise.
- Employees are referenced by `employment_id` or `person_id`. The employee number is display text, never a join key.
- Types: money `decimal(12,2)`; hours and days `decimal(6,2)`; `date`, `time`, `dateTime` for dates and times; booleans `boolean`.
- Status and type columns are string-backed PHP enums stored as upper-case text (`'ACTIVE'`), cast on the model.
- Effective-dated tables have `effective_from` (date, not null) and `effective_to` (date, null = open). Ranges are inclusive and may not overlap for the same owner. Always insert through `EffectiveDating::insert()`.
- Application timezone is `Asia/Colombo`.

## Code rules

- Business rules live in service classes under `app/Services`. Livewire components validate input, call a service, and show the result.
- A rule violation throws a named exception extending `App\Exceptions\Domain\DomainException`. Components catch it and show its message.
- A service method that writes more than one row wraps the work in `DB::transaction()`.
- Every model on a business table uses the `Auditable` trait. Every model with a `company_id` column uses `BelongsToCompany`.
- Every model has a factory. Factories produce valid rows with no arguments.
- Every route has `auth` middleware and a permission check (`can:` middleware or `$this->authorize()`).
- Use the layout components and classes described in `STYLES_GUIDE.md`. Do not invent a new look.
- User-facing text is plain English, sentence case.

## Test rules

- Feature tests use `RefreshDatabase` against the MySQL test database `ecw_hrmis_test`. Not SQLite.
- Test names and assertions come from the task file. You may add more tests; you may not remove or loosen the specified ones.
- Shared helpers live in `tests/Support/`. Use them rather than re-creating fixtures.
- A test that needs a table from a later task uses a probe model in `tests/Support/Probe/` as the task describes.

## Reporting back

When you finish a task, reply with:

1. the tests you added and the result of `php artisan test` (counts);
2. the result of `./vendor/bin/pint --test`;
3. the files you created or changed;
4. anything in the task you could not do, or did differently, and why.
