# Task 02 — Users, roles and company scope

**Depends on:** 01. **Read first:** `AGENTS.md`; in `ecw_reporting_app`: `app/Livewire/Login.php`, `app/Reporting/BranchContext.php`, `app/Livewire/BranchSelector.php`, `Modules/AccessControl/`.

## Goal

People can log in. Each user is limited to the companies they are assigned. Any model marked as company-owned is filtered automatically.

## Files

- Create module `Modules/Organisation` with only: `app/Models/Company.php`, `database/factories/CompanyFactory.php`, migration `create_companies_table`.
- Create: `app/Support/Company/CompanyContext.php`, `CompanyScope.php`, `BelongsToCompany.php`.
- Modify: `app/Models/User.php`, `database/factories/UserFactory.php`; migrations `add_hr_columns_to_users_table`, `create_user_companies_table`, spatie permission tables.
- Create: `app/Livewire/Login.php`, `app/Livewire/CompanySelector.php` and views; `routes/web.php` login/logout.
- Create module `Modules/AccessControl` — copy from `ecw_reporting_app`, replacing branch with company.
- Create: `database/seeders/RolesAndPermissionsSeeder.php`, `database/seeders/AdminUserSeeder.php`.
- Create: `tests/Support/Probe/ScopedProbe.php`, `tests/Support/Probe/migrations/…_create_probe_scoped_table.php`.
- Tests: `tests/Feature/LoginTest.php`, `tests/Feature/CompanyScopeTest.php`.

## Schema

**`companies`**

| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `code` | string(10) | unique |
| `name` | string(150) | |
| `employee_no_prefix` | string(10) | unique |
| `employee_no_padding` | unsigned tinyint | default 3 |
| `next_employee_seq` | unsigned int | default 1 |
| `epf_employer_no` | string(30) null | |
| `etf_employer_no` | string(30) null | |
| `tin` | string(30) null | |
| `is_active` | boolean | default true |
| timestamps | | |

**`users`** — add: `person_id` unsigned bigint null (foreign key is added in Task 08), `is_active` boolean default true, `all_companies` boolean default false, `must_change_password` boolean default true.

**`user_companies`** — `user_id` FK cascade on delete, `company_id` FK, unique (`user_id`, `company_id`).

**`probe_scoped`** (test only) — `id`, `company_id` FK, `name` string, `account_no` string null, timestamps.

## Seed data

Roles: `super-admin`, `hr-manager`, `hr-officer`, `payroll-officer`, `auditor`.

Permissions: `org.view`, `org.manage`, `employee.view`, `employee.create`, `employee.update`, `employee.exit`, `employee.bank.view`, `employee.bank.manage`, `employee.document.view`, `employee.document.manage`, `attendance.device.manage`, `attendance.punch.view`, `approval.act`, `import.run`, `access-control.view`, `audit.view`.

| Role | Permissions |
|---|---|
| `super-admin` | all |
| `hr-manager` | all except `access-control.view`, `import.run` |
| `hr-officer` | `org.view`, `employee.view`, `employee.create`, `employee.update`, `employee.document.view`, `employee.document.manage`, `attendance.punch.view` |
| `payroll-officer` | `org.view`, `employee.view`, `employee.bank.view`, `employee.bank.manage`, `attendance.punch.view` |
| `auditor` | `org.view`, `employee.view`, `attendance.punch.view`, `audit.view` |

Admin user: `admin@ecw.local`, role `super-admin`, `all_companies = true`, `must_change_password = true`; password from env `ADMIN_INITIAL_PASSWORD` (seeder fails with a clear message if it is not set).

## Interfaces (must match exactly)

```php
namespace App\Support\Company;

final class CompanyContext
{
    /** Company ids the logged-in user may see. All active companies when user->all_companies. [] when no user. */
    public static function permittedIds(): array;

    /** Currently selected company id (session key 'company_id'), or null = all permitted. */
    public static function id(): ?int;

    /** Select a company, or null for all. Throws AuthorizationException if $companyId is not permitted. */
    public static function set(?int $companyId): void;
}

/** Global scope: where company_id in permittedIds(); and = id() when one is selected.
 *  Does nothing when running in console or when no user is authenticated. */
final class CompanyScope implements \Illuminate\Database\Eloquent\Scope {}

/** Trait for models with a company_id column. Adds CompanyScope and company(): BelongsTo. */
trait BelongsToCompany {}
```

`User`: `companies(): BelongsToMany`. `UserFactory` states: `forCompanies(array $companies)`, `allCompanies()`, `withRole(string $role)`, `withPermissions(string ...$names)`, `inactive()`.

## Rules

- Login: email + password; inactive users are refused with "Your account is inactive."; 5 failed attempts per minute per email+IP then throttled.
- When `must_change_password` is true, every page redirects to a change-password page until it is changed (minimum 10 characters).
- `CompanySelector` in the navbar lists permitted companies plus "All companies" (only when the user has more than one). Changing it calls `CompanyContext::set()` and reloads the page.
- Route model binding respects the scope: a record of a company the user cannot see resolves to 404.

## Tests

`tests/Feature/LoginTest.php`

```php
public function test_valid_login_reaches_dashboard(): void
{
    $u = User::factory()->allCompanies()->create(['password' => Hash::make('secret-pass-1'), 'must_change_password' => false]);
    Livewire::test(Login::class)->set('email', $u->email)->set('password', 'secret-pass-1')->call('login')->assertRedirect('/');
    $this->assertAuthenticatedAs($u);
}

public function test_wrong_password_is_refused(): void
{
    $u = User::factory()->create(['password' => Hash::make('secret-pass-1')]);
    Livewire::test(Login::class)->set('email', $u->email)->set('password', 'nope')->call('login')->assertHasErrors('email');
    $this->assertGuest();
}

public function test_inactive_user_is_refused(): void
{
    $u = User::factory()->inactive()->create(['password' => Hash::make('secret-pass-1')]);
    Livewire::test(Login::class)->set('email', $u->email)->set('password', 'secret-pass-1')->call('login')->assertHasErrors('email');
    $this->assertGuest();
}

public function test_user_who_must_change_password_is_redirected(): void
{
    $u = User::factory()->allCompanies()->create(['must_change_password' => true]);
    $this->actingAs($u)->get('/')->assertRedirect(route('password.change'));
}
```

`tests/Feature/CompanyScopeTest.php`

```php
public function test_user_sees_only_permitted_company_rows(): void
{
    [$a, $b] = Company::factory()->count(2)->create()->all();
    ScopedProbe::factory()->create(['company_id' => $a->id]);
    ScopedProbe::factory()->create(['company_id' => $b->id]);

    $this->actingAs(User::factory()->forCompanies([$a])->create());

    $this->assertSame(1, ScopedProbe::count());
    $this->assertSame($a->id, ScopedProbe::first()->company_id);
}

public function test_all_companies_user_sees_everything_until_one_is_selected(): void
{
    [$a, $b] = Company::factory()->count(2)->create()->all();
    ScopedProbe::factory()->create(['company_id' => $a->id]);
    ScopedProbe::factory()->create(['company_id' => $b->id]);

    $this->actingAs(User::factory()->allCompanies()->create());
    $this->assertSame(2, ScopedProbe::count());

    CompanyContext::set($b->id);
    $this->assertSame(1, ScopedProbe::count());

    CompanyContext::set(null);
    $this->assertSame(2, ScopedProbe::count());
}

public function test_selecting_a_company_the_user_lacks_is_refused(): void
{
    [$a, $b] = Company::factory()->count(2)->create()->all();
    $this->actingAs(User::factory()->forCompanies([$a])->create());

    $this->expectException(\Illuminate\Auth\Access\AuthorizationException::class);
    CompanyContext::set($b->id);
}

public function test_find_on_another_companys_row_returns_null(): void
{
    [$a, $b] = Company::factory()->count(2)->create()->all();
    $other = ScopedProbe::factory()->create(['company_id' => $b->id]);

    $this->actingAs(User::factory()->forCompanies([$a])->create());
    $this->assertNull(ScopedProbe::find($other->id));
}

public function test_scope_is_not_applied_without_a_user(): void
{
    $a = Company::factory()->create();
    ScopedProbe::factory()->count(2)->create(['company_id' => $a->id]);
    $this->assertSame(2, ScopedProbe::count());
}

public function test_inactive_company_is_not_permitted_for_all_companies_user(): void
{
    $active = Company::factory()->create();
    $inactive = Company::factory()->create(['is_active' => false]);
    $this->actingAs(User::factory()->allCompanies()->create());
    $this->assertSame([$active->id], CompanyContext::permittedIds());
}
```

## Done when

- All tests above pass; suite and Pint clean.
- `php artisan migrate:fresh --seed` creates the roles, permissions and admin user.
- By hand: log in as admin, forced to change password, see the Users and Roles screens, create a user limited to one company.

## Do not

- Do not create other Organisation tables (locations, org units, jobs, grades) — Task 06.
- Do not add a second password column or store any password unhashed.
- Do not seed companies here.
