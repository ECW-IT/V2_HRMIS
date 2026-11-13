# Task 01 — Project skeleton

**Depends on:** nothing. **Read first:** `AGENTS.md`; `ecw_reporting_app/composer.json`, `package.json`, `STYLES_GUIDE.md`, `resources/views/components/layouts/`.

## Goal

An empty, running Laravel application with the team's design system, a module structure, a MySQL test database and a passing pipeline.

## Before the agent starts (human)

- Repository `ecw_hrmis/` exists; databases `ecw_hrmis` and `ecw_hrmis_test` exist; `.env` has the connection for `ecw_hrmis`.
- `ecw_reporting_app` is open in the same workspace.

## Files

- Create: the Laravel application; `STYLES_GUIDE.md`; `phpunit.xml`; `.gitignore`; `README.md`; CI workflow file used by the team.
- Create: `app/Exceptions/Domain/DomainException.php`.
- Create: `tests/TestCase.php`, `tests/Feature/SmokeTest.php`.
- Copy from `ecw_reporting_app`: `resources/css`, `resources/js/app.js`, `resources/views/components/` (layouts `app`, `module`, `guest`; alert; flash-alerts; module-launcher), `vite.config.js`, `tailwind` setup, `STYLES_GUIDE.md`.

## Steps

- [ ] Install Laravel with the **same versions** as `ecw_reporting_app/composer.json` for: `laravel/framework`, `livewire/livewire`, `nwidart/laravel-modules`, `spatie/laravel-permission`, `barryvdh/laravel-dompdf`, `maatwebsite/excel`; dev: `phpunit/phpunit`, `laravel/pint`. Same npm dev dependencies as its `package.json`.
- [ ] `config/app.php`: `'timezone' => 'Asia/Colombo'`.
- [ ] `config/database.php`: connection `mysql` with `'strict' => true`, `'charset' => 'utf8mb4'`, `'collation' => 'utf8mb4_unicode_ci'`, `'engine' => 'InnoDB'`. Add two more connections, `legacy_hrm` and `legacy_payroll`, reading `LEGACY_HRM_*` and `LEGACY_PAYROLL_*` env keys (used only in Task 14).
- [ ] `phpunit.xml`: `DB_CONNECTION=mysql`, `DB_DATABASE=ecw_hrmis_test`; test suites include `tests/Feature`, `tests/Unit`, `Modules/*/tests/Feature`, `Modules/*/tests/Unit`.
- [ ] `composer.json` autoload-dev: `"Tests\\": "tests/"`. Configure nwidart so modules live in `Modules/` with code under `Modules/<Name>/app` (same as the reporting app's `Modules/`).
- [ ] `app/Exceptions/Domain/DomainException.php`: `abstract class DomainException extends \RuntimeException {}`.
- [ ] `AppServiceProvider::boot()`: when `app()->environment('testing')`, call `$this->loadMigrationsFrom(base_path('tests/Support/Probe/migrations'))`. Create that empty folder with a `.gitkeep`.
- [ ] Copy the design system files listed above. The home route `/` shows a dashboard page using `x-layouts.app` behind `auth` middleware; `/login` shows a placeholder page using `x-layouts.guest` (the real login comes in Task 02).
- [ ] `.gitignore` contains: `.env`, `/vendor`, `/node_modules`, `*.zip`, `/storage/app/private`, `/public/build`.
- [ ] CI: on every push run `composer install --no-interaction`, `./vendor/bin/pint --test`, `php artisan test` against a MySQL service.
- [ ] `README.md`: how to install, migrate, run (`composer run dev`) and test.

## Tests

`tests/Feature/SmokeTest.php`

```php
public function test_guest_is_redirected_to_login(): void
{
    $this->get('/')->assertRedirect('/login');
}

public function test_login_page_loads(): void
{
    $this->get('/login')->assertOk();
}

public function test_database_is_mysql_in_strict_utf8mb4(): void
{
    $this->assertSame('mysql', DB::connection()->getDriverName());
    $this->assertSame('utf8mb4', config('database.connections.mysql.charset'));
    $this->assertTrue(config('database.connections.mysql.strict'));
    $this->assertSame('Asia/Colombo', config('app.timezone'));
}
```

## Done when

- `php artisan test` passes (3 tests); `./vendor/bin/pint --test` is clean; CI is green.
- A fresh clone runs with `composer install && npm install && php artisan migrate && composer run dev` and shows the login page styled like the reporting app.
- `git ls-files` shows no `.env`, no `vendor/`, no zip files.

## Do not

- Do not add authentication, models or modules yet.
- Do not restyle or "improve" the copied design system.
