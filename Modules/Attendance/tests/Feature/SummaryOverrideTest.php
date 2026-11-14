<?php

namespace Modules\Attendance\Tests\Feature;

use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\ApprovalDefinitionSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Attendance\Enums\OverrideField;
use Modules\Attendance\Exceptions\InvalidOverride;
use Modules\Attendance\Models\SummaryOverride;
use Modules\Attendance\Services\PeriodCloseService;
use Modules\Attendance\Services\SummaryService;
use Modules\Leave\Database\Seeders\LeaveSeeder;
use Tests\Support\HrFixtures;
use Tests\Support\PeriodSummaryHelpers;
use Tests\TestCase;

class SummaryOverrideTest extends TestCase
{
    use HrFixtures;
    use PeriodSummaryHelpers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2027-01-05 09:00:00');
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(LeaveSeeder::class);
        $this->seed(ApprovalDefinitionSeeder::class);
    }

    public function test_override_replaces_only_its_field_and_is_flagged(): void
    {
        $e = $this->profiled('2026-01-01');
        $this->fullDays($e, '2026-12-01', 22);
        Carbon::setTestNow('2026-12-30 09:00:00');
        app(SummaryService::class)->setOverride($e, $this->dec($e), OverrideField::NOPAY_DAYS, 1, 'Two days were company shutdown', $this->hr($e));
        Carbon::setTestNow('2027-01-05 09:00:00');
        $f = $this->fig($e);
        $this->assertSame([1.0, 22.0, 22.0, true], [$f->nopayDays, $f->workedDays, $f->creditedDays, $f->hasOverride]);
    }

    public function test_second_override_replaces_the_first(): void
    {
        $e = $this->profiled('2026-01-01');
        $this->setExpected($e, 25);
        Carbon::setTestNow('2026-12-30 09:00:00');
        $svc = app(SummaryService::class);
        $period = $this->dec($e);
        $hr = $this->hr($e);
        $svc->setOverride($e, $period, OverrideField::NOPAY_DAYS, 1, 'Two days were company shutdown', $hr);
        $svc->setOverride($e, $period, OverrideField::NOPAY_DAYS, 0.5, 'Corrected after the shutdown review', $hr);
        Carbon::setTestNow('2027-01-05 09:00:00');

        $this->assertSame(1, SummaryOverride::query()->whereNull('cancelled_at')->count());
        $this->assertSame(1, SummaryOverride::query()->whereNotNull('cancelled_at')->count());
        $this->assertSame(0.5, (float) SummaryOverride::query()->whereNull('cancelled_at')->sole()->value);
    }

    public function test_cancelled_override_no_longer_applies(): void
    {
        $e = $this->profiled('2026-01-01');
        $this->fullDays($e, '2026-12-01', 22);
        Carbon::setTestNow('2026-12-30 09:00:00');
        $svc = app(SummaryService::class);
        $override = $svc->setOverride($e, $this->dec($e), OverrideField::NOPAY_DAYS, 1, 'Two days were company shutdown', $this->hr($e));
        $svc->cancelOverride($override, $this->hr($e));
        Carbon::setTestNow('2027-01-05 09:00:00');

        $f = $this->fig($e);
        $this->assertSame([3.0, false], [$f->nopayDays, $f->hasOverride]);
        $this->assertNotNull($override->fresh()->cancelled_at);
    }

    public function test_invalid_overrides_are_refused(): void
    {
        $e = $this->profiled('2026-01-01');
        $this->setExpected($e, 25);
        $svc = app(SummaryService::class);
        $period = $this->dec($e);
        $hr = $this->hr($e);
        $officer = User::factory()->forCompanies([$e->company])->withRole('hr-officer')->create(['must_change_password' => false]);

        foreach ([
            ['short', 1.0],
            ['A long enough reason', -1.0],
            ['A long enough reason', 0.3],
            ['A long enough reason', 32.0],
        ] as [$reason, $value]) {
            try {
                $svc->setOverride($e, $period, OverrideField::NOPAY_DAYS, $value, $reason, $hr);
                $this->fail();
            } catch (InvalidOverride) {
            }
        }

        try {
            $svc->setOverride($e, $period, OverrideField::LATE_MINUTES_CHARGEABLE, 10.5, 'A long enough reason', $hr);
            $this->fail();
        } catch (InvalidOverride) {
        }

        try {
            $svc->setOverride($e, $period, OverrideField::NOPAY_DAYS, 1, 'A long enough reason', $officer);
            $this->fail();
        } catch (AuthorizationException) {
        }

        $this->fullDays($e, '2026-12-01', 25);
        $closed = app(PeriodCloseService::class)->close($period->fresh(), $hr);

        try {
            $svc->setOverride($e, $closed, OverrideField::NOPAY_DAYS, 1, 'A long enough reason', $hr);
            $this->fail();
        } catch (InvalidOverride) {
        }

        $this->assertSame(0, SummaryOverride::count());
    }
}
