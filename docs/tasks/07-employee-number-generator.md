# Task 07 — Employee number generator

**Depends on:** 06. **Read first:** `AGENTS.md`.

## Goal

The system — never a person — issues employee numbers: company prefix + zero-padded sequence, no spaces. Two simultaneous hires can never receive the same number.

Background: the old systems hold six formats of the same number (`ECW 035`, `ECW035`, `ECW_066`, `ecw_066`…) and duplicates. This task ends that.

## Files

- Create module `Modules/CoreHr` (empty apart from this task).
- Create: `Modules/CoreHr/app/Services/EmployeeNumberGenerator.php`.
- Test: `Modules/CoreHr/tests/Feature/EmployeeNumberGeneratorTest.php`.

## Interfaces (must match exactly)

```php
namespace Modules\CoreHr\Services;

final class EmployeeNumberGenerator
{
    /**
     * Call it inside the caller's DB transaction, so the number and the row that uses it commit together.
     * Locks the company row FOR UPDATE, builds prefix + str_pad(seq, padding, '0', STR_PAD_LEFT),
     * skips forward while $isTaken($candidate) is true, stores next_employee_seq = used seq + 1, returns the number.
     */
    public function next(Company $company, ?\Closure $isTaken = null): string;

    /** Upper-case; remove spaces, underscores, hyphens and dots. */
    public static function normalise(string $raw): string;

    /** Trailing digits of a normalised number as int, or null when there are none. 'ECW035' -> 35. */
    public static function sequenceOf(string $normalised): ?int;
}
```

`$isTaken` receives the candidate string and returns bool. When null, nothing is treated as taken. (Task 09 passes a closure that checks the `employments` table; this task has no such table.)

A sequence longer than the padding is not truncated: prefix `ECW`, padding 3, seq 1204 → `ECW1204`.

## Tests

```php
private function gen(Company $c, ?\Closure $isTaken = null): string
{
    return DB::transaction(fn () => app(EmployeeNumberGenerator::class)->next($c, $isTaken));
}

public function test_format_uses_company_prefix_and_padding(): void
{
    $c = Company::factory()->create(['employee_no_prefix' => 'ECW', 'employee_no_padding' => 3, 'next_employee_seq' => 7]);
    $this->assertSame('ECW007', $this->gen($c));
    $this->assertSame(8, $c->fresh()->next_employee_seq);
}

public function test_sequence_longer_than_padding_is_not_truncated(): void
{
    $c = Company::factory()->create(['employee_no_prefix' => 'ECW', 'employee_no_padding' => 3, 'next_employee_seq' => 1204]);
    $this->assertSame('ECW1204', $this->gen($c));
}

public function test_numbers_already_taken_are_skipped(): void
{
    $c = Company::factory()->create(['employee_no_prefix' => 'ECW', 'employee_no_padding' => 3, 'next_employee_seq' => 7]);
    $taken = ['ECW007', 'ECW008'];
    $this->assertSame('ECW009', $this->gen($c, fn (string $n) => in_array($n, $taken, true)));
    $this->assertSame(10, $c->fresh()->next_employee_seq);
}

public function test_fifty_calls_return_fifty_distinct_numbers(): void
{
    $c = Company::factory()->create(['employee_no_prefix' => 'ECW', 'next_employee_seq' => 1]);
    $numbers = collect(range(1, 50))->map(fn () => $this->gen($c->fresh()));
    $this->assertCount(50, $numbers->unique());
    $this->assertSame(51, $c->fresh()->next_employee_seq);
}

public function test_companies_have_independent_sequences(): void
{
    $a = Company::factory()->create(['employee_no_prefix' => 'ECW', 'next_employee_seq' => 5]);
    $b = Company::factory()->create(['employee_no_prefix' => 'ECN', 'next_employee_seq' => 1]);
    $this->assertSame('ECW005', $this->gen($a));
    $this->assertSame('ECN001', $this->gen($b));
}

public function test_normalise(): void
{
    $this->assertSame('ECW066', EmployeeNumberGenerator::normalise('ecw_066'));
    $this->assertSame('ECW035', EmployeeNumberGenerator::normalise('ECW 035'));
    $this->assertSame('ECW035', EmployeeNumberGenerator::normalise(' ECW-035 '));
    $this->assertSame('INT012', EmployeeNumberGenerator::normalise('INT 012'));
    $this->assertSame('1042', EmployeeNumberGenerator::normalise('1042'));
}

public function test_sequence_of(): void
{
    $this->assertSame(35, EmployeeNumberGenerator::sequenceOf('ECW035'));
    $this->assertSame(1204, EmployeeNumberGenerator::sequenceOf('ECW1204'));
    $this->assertSame(1042, EmployeeNumberGenerator::sequenceOf('1042'));
    $this->assertNull(EmployeeNumberGenerator::sequenceOf('ECW'));
}
```

## Done when

All 7 tests pass; suite and Pint clean.

## Do not

- Do not add a screen or any way to type or edit an employee number.
- Do not create the `employments` table — Task 09.
