# Task 06 — Organisation

**Depends on:** 02, 03. **Read first:** `AGENTS.md`; `STYLES_GUIDE.md`; `Modules/AccessControl` (as the pattern for list + form screens).

## Goal

Master data every employee record points to: locations, the department/section tree per company, jobs and grades. Plus screens to maintain them and a clearly marked placeholder seed.

## Files

- In `Modules/Organisation`: models `Location`, `OrgUnit`, `Job`, `Grade`; enum `OrgUnitType`; services `OrgUnitService`, `JobService`; exceptions `InvalidOrgMove`, `OrgUnitInUse`, `DuplicateName`; factories; migrations; Livewire `CompaniesIndex`, `LocationsIndex`, `OrgTree`, `JobsIndex`, `GradesIndex` + views; routes; seeder `OrganisationSeeder`.
- Create: `database/seeders/data/companies.php`, `grades.php`, `locations.php`.
- Tests: `Modules/Organisation/tests/Feature/OrgUnitServiceTest.php`, `JobServiceTest.php`, `OrganisationScreensTest.php`.

## Schema

All tables have `id` and timestamps. All models use `Auditable`.

**`locations`** — `code` string(10) unique, `name` string(100), `address` string(255) null, `is_active` boolean default true.

**`org_units`** — `company_id` FK, `parent_id` FK `org_units` null, `code` string(20), `name` string(150), `type` string(12), `head_employment_id` unsigned bigint null (FK added in Task 09), `is_active` boolean default true. Unique (`company_id`, `code`). Uses `BelongsToCompany`.

**`jobs`** — table name **`hr_jobs`** (Laravel's queue already uses `jobs`). `code` string(20) unique, `name` string(150), `name_key` string(150) unique, `is_active` boolean default true. Model `Job` with `protected $table = 'hr_jobs'`.

**`grades`** — `code` string(10) unique, `name` string(100) unique, `rank` unsigned smallint unique, `is_active` boolean default true.

`OrgUnitType`: `DEPARTMENT`, `SECTION`, `SUBSECTION`.

## Placeholder seed (replaced by Task 00 outputs)

Each data file starts with the comment `// PLACEHOLDER — replace with the list agreed in Task 00. Do not treat as real.`

```php
// companies.php
[['code' => 'CO1', 'name' => 'Company One (placeholder)', 'employee_no_prefix' => 'ECW', 'employee_no_padding' => 3],
 ['code' => 'CO2', 'name' => 'Company Two (placeholder)', 'employee_no_prefix' => 'ECN', 'employee_no_padding' => 3]]
// locations.php
[['code' => 'NIT', 'name' => 'Nittambuwa'], ['code' => 'NEG', 'name' => 'Negombo']]
// grades.php
[['code' => 'G1', 'name' => 'Grade 1 (placeholder)', 'rank' => 1], ['code' => 'G2', 'name' => 'Grade 2 (placeholder)', 'rank' => 2],
 ['code' => 'G3', 'name' => 'Grade 3 (placeholder)', 'rank' => 3], ['code' => 'G4', 'name' => 'Grade 4 (placeholder)', 'rank' => 4]]
```

`OrganisationSeeder` uses `updateOrCreate` on `code`, so it can be re-run. No org units or jobs are seeded.

## Interfaces (must match exactly)

```php
namespace Modules\Organisation\Services;

final class OrgUnitService
{
    public function create(array $data): OrgUnit;                    // keys: company_id, parent_id?, code, name, type
    public function update(OrgUnit $unit, array $data): OrgUnit;     // name, code, type only
    public function move(OrgUnit $unit, ?OrgUnit $newParent): OrgUnit;
    public function deactivate(OrgUnit $unit): void;
    /** Ids of $unit and everything beneath it. */
    public function descendantIds(OrgUnit $unit): array;
    /** Whether any current assignment uses one of the given org units. Returns false until Task 09 replaces the body. */
    public function hasCurrentAssignments(array $orgUnitIds): bool;
}

final class JobService
{
    public function create(array $data): Job;        // code, name
    public function update(Job $job, array $data): Job;
    public static function nameKey(string $name): string;   // lower-case, trim, collapse inner whitespace to one space
}
```

## Rules

- `create`/`move`: parent must be in the **same company** → else `InvalidOrgMove`. A unit cannot become its own parent or move under one of its descendants → `InvalidOrgMove`.
- Type order: a `DEPARTMENT` has no parent or a `DEPARTMENT` parent; a `SECTION`'s parent is a `DEPARTMENT`; a `SUBSECTION`'s parent is a `SECTION` → else `InvalidOrgMove`.
- Duplicate `code` within a company → `DuplicateName`. The same code in another company is allowed.
- `deactivate` is refused with `OrgUnitInUse` when (a) the unit has any **active child** ("Deactivate the units beneath it first."), or (b) `hasCurrentAssignments(descendantIds($unit))` is true ("Employees are still assigned here.").
- Jobs: `name_key = JobService::nameKey(name)`; a duplicate `name_key` → `DuplicateName`. This stops "Driver" and " driver " both existing.
- Nothing is deleted; records are deactivated. Inactive records are hidden from pickers but stay on existing data.

## Screens

All use `x-layouts.module`. View permission `org.view`; create/edit/deactivate need `org.manage`.

| Screen | Route name | Content |
|---|---|---|
| Companies | `org.companies.index` | **super-admin only.** Table: code, name, prefix, padding, next number, EPF no., ETF no., active. Form fields: all of those except next number (read-only). Prefix cannot be edited once the company has any employment (enforced from Task 09; until then always editable). |
| Locations | `org.locations.index` | Table: code, name, address, active. Form: code, name, address. |
| Org tree | `org.units.index` | Uses the selected company (requires one to be selected; otherwise shows "Select a company"). Indented expandable tree: code, name, type badge, active. Row actions: Add child, Edit, Move (pick new parent from same company), Deactivate / Activate. "Add department" button at top. Toggle "Show inactive". |
| Jobs | `org.jobs.index` | Search box; table: code, name, active. Form: code, name. |
| Grades | `org.grades.index` | Table ordered by rank: rank, code, name, active. Form: code, name, rank. |

Validation messages come from the domain exception text.

## Tests

`Modules/Organisation/tests/Feature/OrgUnitServiceTest.php` — `setUp`: `$this->svc = app(OrgUnitService::class); $this->c = Company::factory()->create();` helper `unit(string $code, OrgUnitType $type = DEPARTMENT, ?OrgUnit $parent = null, ?Company $c = null)` calling `svc->create`.

```php
public function test_create_department_section_subsection_chain(): void
{
    $d = $this->unit('PROD'); $s = $this->unit('PAINT', OrgUnitType::SECTION, $d); $ss = $this->unit('PREP', OrgUnitType::SUBSECTION, $s);
    $this->assertSame($s->id, $ss->parent_id);
    $this->assertEqualsCanonicalizing([$d->id, $s->id, $ss->id], $this->svc->descendantIds($d));
    $this->assertSame([$ss->id], $this->svc->descendantIds($ss));
}

public function test_parent_in_another_company_is_refused(): void
{
    $other = $this->unit('PROD', c: Company::factory()->create());
    $this->expectException(InvalidOrgMove::class);
    $this->unit('PAINT', OrgUnitType::SECTION, $other, $this->c);
}

public function test_section_must_sit_under_a_department(): void
{
    $this->expectException(InvalidOrgMove::class);
    $this->unit('PAINT', OrgUnitType::SECTION, null);
}

public function test_moving_a_unit_under_its_own_descendant_is_refused(): void
{
    $a = $this->unit('A'); $b = $this->unit('B', OrgUnitType::DEPARTMENT, $a);
    $this->expectException(InvalidOrgMove::class);
    $this->svc->move($a, $b);
}

public function test_moving_a_unit_under_itself_is_refused(): void
{
    $a = $this->unit('A');
    $this->expectException(InvalidOrgMove::class);
    $this->svc->move($a, $a);
}

public function test_move_changes_parent(): void
{
    $a = $this->unit('A'); $b = $this->unit('B'); $s = $this->unit('S', OrgUnitType::SECTION, $a);
    $this->assertSame($b->id, $this->svc->move($s, $b)->parent_id);
}

public function test_duplicate_code_is_refused_within_a_company_but_allowed_across(): void
{
    $this->unit('PROD');
    $this->assertNotNull($this->unit('PROD', c: Company::factory()->create()));
    $this->expectException(DuplicateName::class);
    $this->unit('PROD');
}

public function test_unit_with_active_children_cannot_be_deactivated(): void
{
    $d = $this->unit('PROD'); $this->unit('PAINT', OrgUnitType::SECTION, $d);
    $this->expectException(OrgUnitInUse::class);
    $this->svc->deactivate($d);
}

public function test_leaf_unit_can_be_deactivated(): void
{
    $d = $this->unit('PROD');
    $this->svc->deactivate($d);
    $this->assertFalse($d->fresh()->is_active);
}
```

`JobServiceTest.php`

```php
public function test_name_key_normalises_case_and_spaces(): void
{
    $this->assertSame('cnc machine operator', JobService::nameKey('  CNC   Machine Operator '));
}

public function test_job_name_must_be_unique_ignoring_case_and_spaces(): void
{
    app(JobService::class)->create(['code' => 'DRV', 'name' => 'Driver']);
    $this->expectException(DuplicateName::class);
    app(JobService::class)->create(['code' => 'DRV2', 'name' => ' driver ']);
}

public function test_renaming_a_job_to_an_existing_name_is_refused(): void
{
    $svc = app(JobService::class);
    $svc->create(['code' => 'DRV', 'name' => 'Driver']);
    $b = $svc->create(['code' => 'SUP', 'name' => 'Supervisor']);
    $this->expectException(DuplicateName::class);
    $svc->update($b, ['name' => 'DRIVER']);
}
```

`OrganisationScreensTest.php`

```php
public function test_each_screen_needs_org_view(): void
// for routes org.locations.index, org.units.index, org.jobs.index, org.grades.index: user without permission -> 403; with org.view -> 200
public function test_companies_screen_is_super_admin_only(): void       // hr-manager -> 403, super-admin -> 200
public function test_user_with_view_only_cannot_save(): void            // Livewire JobsIndex ->call('save') with org.view only -> assertForbidden
public function test_org_tree_lists_only_the_selected_companys_units(): void
// units in two companies; CompanyContext::set($a) -> sees 'A-PROD', does not see 'B-PROD'
public function test_grade_rank_must_be_unique(): void                  // second grade with rank 1 -> validation error on 'rank'
public function test_seeder_can_run_twice(): void                       // run OrganisationSeeder twice -> 2 companies, 2 locations, 4 grades
```

## Done when

All tests pass; suite and Pint clean; by hand: build a three-level tree for CO1, move a section, fail to deactivate a department with sections, add a job, fail to add the same job with different capitals.

## Do not

- Do not create positions or cost centres.
- Do not seed real company names, org units or jobs.
- Do not name the table `jobs`.
