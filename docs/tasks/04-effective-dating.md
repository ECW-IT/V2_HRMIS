# Task 04 — Effective dating

**Depends on:** 01. **Read first:** `AGENTS.md` (Database rules).

## Goal

A reusable way to store values that change over time (assignment, bank account, later salary) so that history is kept, date ranges never overlap, and "what was true on date X" is one query.

## Files

- Create: `app/Support/Effective/EffectiveDated.php`, `EffectiveDating.php`, `EffectiveDateConflict.php`.
- Create: `tests/Support/Probe/EffectiveProbe.php`, `tests/Support/Probe/migrations/…_create_probe_effective_table.php`.
- Test: `tests/Unit/EffectiveDatingTest.php` (uses the database; extends `Tests\TestCase` with `RefreshDatabase`).

## Schema — `probe_effective` (test only)

`id`, `owner_id` unsigned bigint, `effective_from` date, `effective_to` date null, `label` string, timestamps. Unique (`owner_id`, `effective_from`).

`EffectiveProbe`: uses `EffectiveDated`; `protected string $effectiveOwnerKey = 'owner_id';`; casts both dates to `date`.

## Interfaces (must match exactly)

```php
namespace App\Support\Effective;

/** The model must declare: protected string $effectiveOwnerKey = '<column>'; */
trait EffectiveDated
{
    /** Rows where effective_from <= $date AND (effective_to IS NULL OR effective_to >= $date). */
    public function scopeEffectiveOn(Builder $query, \DateTimeInterface|string $date): Builder;

    /** effectiveOn(today in app timezone). */
    public function scopeCurrent(Builder $query): Builder;

    public function effectiveOwnerKey(): string;
}

final class EffectiveDating
{
    /** Insert $new (unsaved, effective_from set) among its owner's rows. See Rules. Returns the saved model. */
    public static function insert(Model $new): Model;

    /** Set effective_to on the owner's open row. Throws EffectiveDateConflict if $on is before that row's effective_from. No-op if there is no open row. */
    public static function close(string $modelClass, int $ownerId, \DateTimeInterface|string $on): void;

    /** Throws EffectiveDateConflict if any two rows of the owner overlap or a closed row has effective_to < effective_from. */
    public static function assertNoOverlap(string $modelClass, int $ownerId): void;
}

final class EffectiveDateConflict extends \App\Exceptions\Domain\DomainException {}
```

## Rules for `insert()` — all inside one transaction, locking the owner's rows `FOR UPDATE`

Let `F` = `$new->effective_from`.

1. If a row of the same owner has `effective_from = F` → throw `EffectiveDateConflict` ("A record already starts on this date.").
2. If `$new->effective_to` is set and is before `F` → throw `EffectiveDateConflict`.
3. Find the row **containing** `F` (the row effective on `F`). If found, set its `effective_to = F − 1 day`.
4. Find the **next** row (smallest `effective_from > F`). If found, set `$new->effective_to = next.effective_from − 1 day` (overriding any value given). If not found, leave `$new->effective_to` as given (normally null).
5. Save `$new`.

Dates are compared as dates, never as timestamps.

## Tests — `tests/Unit/EffectiveDatingTest.php`

Helpers in the test class:

```php
private function row(string $from, ?string $to, int $owner = 1, string $label = 'x'): EffectiveProbe
{
    return EffectiveProbe::create(['owner_id' => $owner, 'effective_from' => $from, 'effective_to' => $to, 'label' => $label]);
}
private function make(string $from, ?string $to = null, int $owner = 1, string $label = 'new'): EffectiveProbe
{
    return new EffectiveProbe(['owner_id' => $owner, 'effective_from' => $from, 'effective_to' => $to, 'label' => $label]);
}
private function d(?\DateTimeInterface $v): ?string { return $v?->format('Y-m-d'); }
```

```php
public function test_first_row_is_saved_open(): void
{
    $r = EffectiveDating::insert($this->make('2026-01-01'));
    $this->assertTrue($r->exists);
    $this->assertNull($r->effective_to);
}

public function test_new_row_closes_the_open_row_the_day_before(): void
{
    $old = $this->row('2026-01-01', null);
    $new = EffectiveDating::insert($this->make('2026-06-15'));

    $this->assertSame('2026-06-14', $this->d($old->fresh()->effective_to));
    $this->assertNull($new->effective_to);
}

public function test_back_dated_row_is_fitted_between_existing_rows(): void
{
    $first = $this->row('2026-01-01', '2026-05-31');
    $second = $this->row('2026-06-01', null);

    $new = EffectiveDating::insert($this->make('2026-03-01'));

    $this->assertSame('2026-02-28', $this->d($first->fresh()->effective_to));
    $this->assertSame('2026-05-31', $this->d($new->effective_to));
    $this->assertNull($second->fresh()->effective_to);
    EffectiveDating::assertNoOverlap(EffectiveProbe::class, 1);
}

public function test_row_before_all_existing_rows_ends_the_day_before_the_first(): void
{
    $this->row('2026-06-01', null);
    $new = EffectiveDating::insert($this->make('2026-01-01'));
    $this->assertSame('2026-05-31', $this->d($new->effective_to));
}

public function test_same_start_date_is_refused(): void
{
    $this->row('2026-01-01', null);
    $this->expectException(EffectiveDateConflict::class);
    EffectiveDating::insert($this->make('2026-01-01'));
}

public function test_end_before_start_is_refused(): void
{
    $this->expectException(EffectiveDateConflict::class);
    EffectiveDating::insert($this->make('2026-06-01', '2026-05-01'));
}

public function test_owners_are_independent(): void
{
    $other = $this->row('2026-01-01', null, owner: 2);
    EffectiveDating::insert($this->make('2026-06-15', owner: 1));
    $this->assertNull($other->fresh()->effective_to);
}

public function test_effective_on_is_inclusive_at_both_ends(): void
{
    $this->row('2026-01-01', '2026-01-31');
    $this->assertSame(0, EffectiveProbe::effectiveOn('2025-12-31')->count());
    $this->assertSame(1, EffectiveProbe::effectiveOn('2026-01-01')->count());
    $this->assertSame(1, EffectiveProbe::effectiveOn('2026-01-31')->count());
    $this->assertSame(0, EffectiveProbe::effectiveOn('2026-02-01')->count());
}

public function test_effective_on_returns_exactly_one_row_per_owner_after_several_inserts(): void
{
    EffectiveDating::insert($this->make('2026-01-01', label: 'a'));
    EffectiveDating::insert($this->make('2026-04-01', label: 'b'));
    EffectiveDating::insert($this->make('2026-02-01', label: 'c'));

    $this->assertSame('a', EffectiveProbe::effectiveOn('2026-01-15')->sole()->label);
    $this->assertSame('c', EffectiveProbe::effectiveOn('2026-03-31')->sole()->label);
    $this->assertSame('b', EffectiveProbe::effectiveOn('2026-04-01')->sole()->label);
    $this->assertSame('b', EffectiveProbe::effectiveOn('2030-01-01')->sole()->label);
}

public function test_close_sets_the_end_of_the_open_row(): void
{
    $r = $this->row('2026-01-01', null);
    EffectiveDating::close(EffectiveProbe::class, 1, '2026-10-31');
    $this->assertSame('2026-10-31', $this->d($r->fresh()->effective_to));
}

public function test_close_before_the_row_starts_is_refused(): void
{
    $this->row('2026-06-01', null);
    $this->expectException(EffectiveDateConflict::class);
    EffectiveDating::close(EffectiveProbe::class, 1, '2026-05-31');
}

public function test_assert_no_overlap_detects_bad_data(): void
{
    $this->row('2026-01-01', '2026-06-30');
    $this->row('2026-06-01', null);
    $this->expectException(EffectiveDateConflict::class);
    EffectiveDating::assertNoOverlap(EffectiveProbe::class, 1);
}
```

## Done when

All 12 tests pass; suite and Pint clean.

## Do not

- Do not create any real business table here.
- Do not use datetime comparison or timezone conversion on these date columns.
