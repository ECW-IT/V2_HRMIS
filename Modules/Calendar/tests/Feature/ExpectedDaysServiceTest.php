<?php

namespace Modules\Calendar\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Calendar\Exceptions\InvalidExpectedDays;
use Modules\Calendar\Models\PeriodExpectedDays;
use Modules\Calendar\Services\ExpectedDaysService;
use Modules\Calendar\Services\PayPeriodService;
use Modules\Organisation\Models\Company;
use Modules\Organisation\Models\Grade;
use Tests\TestCase;

class ExpectedDaysServiceTest extends TestCase
{
    use RefreshDatabase;

    private ExpectedDaysService $svc;

    protected function setUp(): void
    {
        parent::setUp();

        $this->svc = app(ExpectedDaysService::class);
    }

    public function test_set_get_and_missing(): void
    {
        $p = app(PayPeriodService::class)->ensure(Company::factory()->create(), 2026, 12);
        $g1 = Grade::factory()->create();
        $g2 = Grade::factory()->create();
        Grade::factory()->create(['is_active' => false]);
        $this->svc->set($p, $g1, 24.5);
        $this->assertSame(24.5, $this->svc->for($p, $g1->id));
        $this->assertNull($this->svc->for($p, $g2->id));
        $this->assertSame([$g2->id], $this->svc->missing($p)->pluck('id')->all());
    }

    public function test_setting_again_replaces_the_value(): void
    {
        $p = app(PayPeriodService::class)->ensure(Company::factory()->create(), 2026, 12);
        $g = Grade::factory()->create();
        $this->svc->set($p, $g, 24);
        $this->svc->set($p, $g, 25);
        $this->assertSame(25.0, $this->svc->for($p, $g->id));
        $this->assertSame(1, PeriodExpectedDays::count());
    }

    public function test_invalid_values_are_refused(): void
    {
        $p = app(PayPeriodService::class)->ensure(Company::factory()->create(), 2027, 2);
        $g = Grade::factory()->create();
        foreach ([-1, 28.5, 24.3] as $bad) {
            try {
                $this->svc->set($p, $g, $bad);
                $this->fail((string) $bad);
            } catch (InvalidExpectedDays) {
                $this->addToAssertionCount(1);
            }
        }
        $this->assertSame(28.0, $this->svc->set($p, $g, 28)->days + 0.0);
    }

    public function test_closed_period_cannot_be_changed(): void
    {
        $this->travelTo('2027-01-05');
        $pp = app(PayPeriodService::class);
        $p = $pp->markClosed($pp->ensure(Company::factory()->create(), 2026, 12), null);
        $this->expectException(InvalidExpectedDays::class);
        $this->svc->set($p, Grade::factory()->create(), 24);
    }

    public function test_copy_from_previous_fills_only_empty_grades(): void
    {
        $c = Company::factory()->create();
        $pp = app(PayPeriodService::class);
        $nov = $pp->ensure($c, 2026, 11);
        $dec = $pp->ensure($c, 2026, 12);
        $g1 = Grade::factory()->create();
        $g2 = Grade::factory()->create();
        $this->svc->set($nov, $g1, 24);
        $this->svc->set($nov, $g2, 25);
        $this->svc->set($dec, $g2, 22);

        $this->assertSame(1, $this->svc->copyFromPrevious($dec));
        $this->assertSame(24.0, $this->svc->for($dec, $g1->id));
        $this->assertSame(22.0, $this->svc->for($dec, $g2->id));
    }
}
