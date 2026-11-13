# ECW HRMIS v2

Employee master (and later attendance, leave, payroll) for ECW sister companies.

## Requirements

- PHP 8.3+
- Composer 2
- Node.js 20.19+ or 22.12+
- MySQL 8 / MariaDB 10.6+

Create databases `ecw_hrmis` and `ecw_hrmis_test` before running.

## Install

```bash
composer install
cp .env.example .env
php artisan key:generate
# set DB_* in .env to your MySQL credentials
php artisan migrate
npm install
```

## Run

```bash
composer run dev
```

Or separately: `php artisan serve` and `npm run dev`.

Open `/login` for the placeholder sign-in page. Real authentication arrives in Task 02.

## Test

```bash
php artisan test
./vendor/bin/pint --test
```

Feature tests use MySQL database `ecw_hrmis_test` (see `phpunit.xml`).

## Layout

- `app/Support/` — shared company scope, audit, effective dating (later tasks)
- `Modules/<Name>/` — domain modules (`nwidart/laravel-modules`)
- `docs/tasks/` — build tasks; follow `AGENTS.md`
