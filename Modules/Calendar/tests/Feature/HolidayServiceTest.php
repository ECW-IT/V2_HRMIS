<?php

namespace Modules\Calendar\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Calendar\Enums\HolidayKind;
use Modules\Calendar\Exceptions\InvalidHoliday;
use Modules\Calendar\Models\Holiday;
use Modules\Calendar\Services\HolidayService;
use Modules\Organisation\Models\Company;
use Tests\TestCase;

class HolidayServiceTest extends TestCase
{
    use RefreshDatabase;

    private HolidayService $svc;

    protected function setUp(): void
    {
        parent::setUp();

        $this->svc = app(HolidayService::class);
    }

    public function test_add_and_find(): void
    {
        $c = Company::factory()->create();
        $h = $this->svc->add($c, '2026-12-23', 'Unduvap Full Moon Poya Day', HolidayKind::POYA);
        $this->assertSame($h->id, $this->svc->on($c->id, '2026-12-23')->id);
        $this->assertNull($this->svc->on($c->id, '2026-12-24'));
    }

    public function test_holiday_is_per_company(): void
    {
        $a = Company::factory()->create();
        $b = Company::factory()->create();
        $this->svc->add($a, '2026-12-25', 'Christmas Day', HolidayKind::MERCANTILE);
        $this->assertNull($this->svc->on($b->id, '2026-12-25'));
    }

    public function test_second_holiday_on_the_same_date_is_refused(): void
    {
        $c = Company::factory()->create();
        $this->svc->add($c, '2026-12-25', 'Christmas Day', HolidayKind::MERCANTILE);
        $this->expectException(InvalidHoliday::class);
        $this->svc->add($c, '2026-12-25', 'Company day', HolidayKind::COMPANY);
    }

    public function test_between_is_inclusive_and_keyed_by_date(): void
    {
        $c = Company::factory()->create();
        $this->svc->add($c, '2026-12-01', 'A', HolidayKind::COMPANY);
        $this->svc->add($c, '2026-12-31', 'B', HolidayKind::COMPANY);
        $this->svc->add($c, '2027-01-01', 'C', HolidayKind::COMPANY);
        $this->assertSame(['2026-12-01', '2026-12-31'], array_keys($this->svc->between($c->id, '2026-12-01', '2026-12-31')));
    }

    public function test_copy_year_skips_existing_dates(): void
    {
        $a = Company::factory()->create();
        $b = Company::factory()->create();
        $this->svc->add($a, '2027-01-14', 'Thai Pongal', HolidayKind::PUBLIC);
        $this->svc->add($a, '2027-05-01', 'May Day', HolidayKind::MERCANTILE);
        $this->svc->add($a, '2026-12-25', 'Christmas', HolidayKind::MERCANTILE);
        $this->svc->add($b, '2027-05-01', 'May Day', HolidayKind::MERCANTILE);
        $this->assertSame(1, $this->svc->copyYear($a, $b, 2027));
        $this->assertSame(2, Holiday::withoutGlobalScopes()->where('company_id', $b->id)->count());
    }
}
