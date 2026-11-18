<?php

namespace Modules\CoreHr\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\CoreHr\Services\EpfNumberGenerator;
use Modules\Organisation\Models\Company;
use Tests\TestCase;

class EpfNumberGeneratorTest extends TestCase
{
    use RefreshDatabase;

    private function gen(Company $c, ?\Closure $isTaken = null): string
    {
        return DB::transaction(fn () => app(EpfNumberGenerator::class)->next($c, $isTaken));
    }

    public function test_peek_shows_the_next_number_without_consuming_it(): void
    {
        $c = Company::factory()->create(['next_epf_seq' => 42]);
        $gen = app(EpfNumberGenerator::class);

        $this->assertSame('42', $gen->peek($c));
        $this->assertSame(42, $c->fresh()->next_epf_seq);
        $this->assertSame('42', $this->gen($c));
        $this->assertSame(43, $c->fresh()->next_epf_seq);
    }

    public function test_numbers_already_taken_are_skipped(): void
    {
        $c = Company::factory()->create(['next_epf_seq' => 7]);
        $taken = ['7', '8'];
        $this->assertSame('9', $this->gen($c, fn (string $n) => in_array($n, $taken, true)));
        $this->assertSame(10, $c->fresh()->next_epf_seq);
    }

    public function test_companies_have_independent_sequences(): void
    {
        $a = Company::factory()->create(['next_epf_seq' => 5]);
        $b = Company::factory()->create(['next_epf_seq' => 1]);
        $this->assertSame('5', $this->gen($a));
        $this->assertSame('1', $this->gen($b));
    }
}
