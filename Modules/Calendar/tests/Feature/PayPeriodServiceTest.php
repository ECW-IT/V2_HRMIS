<?php

namespace Modules\Calendar\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Calendar\Enums\HolidayKind;
use Modules\Calendar\Enums\PeriodStatus;
use Modules\Calendar\Exceptions\InvalidHoliday;
use Modules\Calendar\Exceptions\InvalidPeriodAction;
use Modules\Calendar\Exceptions\PeriodNotFound;
use Modules\Calendar\Models\PayPeriod;
use Modules\Calendar\Services\HolidayService;
use Modules\Calendar\Services\PayPeriodService;
use Modules\Organisation\Models\Company;
use Tests\TestCase;

class PayPeriodServiceTest extends TestCase
{
    use RefreshDatabase;

    private PayPeriodService $svc;

    protected function setUp(): void
    {
        parent::setUp();

        $this->svc = app(PayPeriodService::class);
    }

    public function test_ensure_creates_a_whole_calendar_month_once(): void
    {
        $c = Company::factory()->create();
        $p = $this->svc->ensure($c, 2027, 2);
        $this->assertSame(['2027-02-01', '2027-02-28', PeriodStatus::OPEN, 0], [$p->start_date->toDateString(), $p->end_date->toDateString(), $p->status, $p->close_version]);
        $this->assertSame($p->id, $this->svc->ensure($c, 2027, 2)->id);
        $this->assertSame(1, PayPeriod::withoutGlobalScopes()->count());
    }

    public function test_leap_february(): void
    {
        $this->assertSame('2028-02-29', $this->svc->ensure(Company::factory()->create(), 2028, 2)->end_date->toDateString());
    }

    public function test_for_date_finds_the_period_and_fails_clearly_when_missing(): void
    {
        $c = Company::factory()->create();
        $p = $this->svc->ensure($c, 2026, 12);
        $this->assertSame($p->id, $this->svc->forDate($c->id, '2026-12-31')->id);
        $this->expectException(PeriodNotFound::class);
        $this->svc->forDate($c->id, '2027-01-01');
    }

    public function test_ensure_range_covers_every_active_company(): void
    {
        Company::factory()->count(2)->create();
        Company::factory()->create(['is_active' => false]);
        $this->assertSame(6, $this->svc->ensureRange('2026-11-15', '2027-01-03'));
        $this->assertSame(0, $this->svc->ensureRange('2026-11-15', '2027-01-03'));
    }

    public function test_period_cannot_be_closed_before_it_ends(): void
    {
        $this->travelTo('2026-12-31 23:00:00');
        $p = $this->svc->ensure(Company::factory()->create(), 2026, 12);
        $this->expectException(InvalidPeriodAction::class);
        $this->svc->markClosed($p, null);
    }

    public function test_periods_close_in_order(): void
    {
        $this->travelTo('2027-01-05');
        $c = Company::factory()->create();
        $nov = $this->svc->ensure($c, 2026, 11);
        $dec = $this->svc->ensure($c, 2026, 12);

        try {
            $this->svc->markClosed($dec, null);
            $this->fail();
        } catch (InvalidPeriodAction $e) {
            $this->assertStringContainsString('November', $e->getMessage());
        }

        $nov = $this->svc->markClosed($nov, null);
        $this->assertSame([PeriodStatus::CLOSED, 1], [$nov->status, $nov->close_version]);
        $this->assertNotNull($nov->closed_at);
        $this->assertSame(PeriodStatus::CLOSED, $this->svc->markClosed($dec, null)->status);
    }

    public function test_periods_reopen_in_reverse_order_and_keep_their_version(): void
    {
        $this->travelTo('2027-01-05');
        $c = Company::factory()->create();
        $nov = $this->svc->markClosed($this->svc->ensure($c, 2026, 11), null);
        $dec = $this->svc->markClosed($this->svc->ensure($c, 2026, 12), null);

        try {
            $this->svc->markOpen($nov);
            $this->fail();
        } catch (InvalidPeriodAction $e) {
            $this->assertStringContainsString('December', $e->getMessage());
        }

        $dec = $this->svc->markOpen($dec);
        $this->assertSame([PeriodStatus::OPEN, 1], [$dec->status, $dec->close_version]);
        $this->assertSame(2, $this->svc->markClosed($dec, null)->close_version);
    }

    public function test_is_date_locked_follows_the_period_status(): void
    {
        $this->travelTo('2027-01-05');
        $c = Company::factory()->create();
        $p = $this->svc->ensure($c, 2026, 12);
        $this->assertFalse($this->svc->isDateLocked($c->id, '2026-12-15'));
        $this->svc->markClosed($p, null);
        $this->assertTrue($this->svc->isDateLocked($c->id, '2026-12-15'));
        $this->assertFalse($this->svc->isDateLocked($c->id, '2027-01-02'));
    }

    public function test_holiday_cannot_be_changed_in_a_closed_period(): void
    {
        $this->travelTo('2027-01-05');
        $c = Company::factory()->create();
        $this->svc->markClosed($this->svc->ensure($c, 2026, 12), null);
        $this->expectException(InvalidHoliday::class);
        app(HolidayService::class)->add($c, '2026-12-25', 'Christmas Day', HolidayKind::MERCANTILE);
    }
}
