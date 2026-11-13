<?php

namespace Modules\CoreHr\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\CoreHr\Services\EmployeeNumberGenerator;
use Modules\Organisation\Models\Company;
use Tests\TestCase;

class EmployeeNumberGeneratorTest extends TestCase
{
    use RefreshDatabase;

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
}
