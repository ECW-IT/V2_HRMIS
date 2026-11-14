<?php

namespace Modules\Attendance\Tests\Feature;

use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\ApprovalDefinitionSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Attendance\Enums\OverrideField;
use Modules\Attendance\Enums\PunchResolution;
use Modules\Attendance\Exceptions\PeriodCloseBlocked;
use Modules\Attendance\Models\PeriodEvent;
use Modules\Attendance\Models\PeriodTimeSummary;
use Modules\Attendance\Models\Punch;
use Modules\Attendance\Services\AdjustmentService;
use Modules\Attendance\Services\PeriodCloseService;
use Modules\Attendance\Services\SummaryService;
use Modules\Attendance\Services\TimecardService;
use Modules\Calendar\Enums\PeriodStatus;
use Modules\Calendar\Exceptions\InvalidPeriodAction;
use Modules\Leave\Database\Seeders\LeaveSeeder;
use Tests\Support\HrFixtures;
use Tests\Support\PeriodSummaryHelpers;
use Tests\TestCase;

class PeriodCloseTest extends TestCase
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

    public function test_close_writes_one_summary_per_included_employee_and_locks(): void
    {
        $c = $this->company();
        $a = $this->clean($c);
        $b = $this->clean($c);
        $none = $this->profiled('2026-01-01', $c, ['attendance_mode' => 'NO_PUNCH']);
        $this->setExpected($none, 25);
        $p = app(PeriodCloseService::class)->close($this->dec($a), $this->hr($a));

        $this->assertSame([PeriodStatus::CLOSED, 1], [$p->status, $p->close_version]);
        $rows = app(SummaryService::class)->final($p);
        $this->assertEqualsCanonicalizing([$a->id, $b->id, $none->id], $rows->pluck('employment_id')->all());
        $this->assertSame([1], $rows->pluck('version')->unique()->values()->all());
        $this->assertSame('25.00', $rows->firstWhere('employment_id', $a->id)->worked_days);
        $this->assertSame(['CLOSED'], PeriodEvent::where('pay_period_id', $p->id)->pluck('event')->all());
        $this->assertTrue(app(TimecardService::class)->isLocked($c->id, '2026-12-15'));
    }

    public function test_close_is_blocked_and_saves_nothing_when_an_item_fails(): void
    {
        $e = $this->clean($this->company());
        $this->punch($e, '2026-12-30 08:00:00');
        try {
            app(PeriodCloseService::class)->close($this->dec($e), $this->hr($e));
            $this->fail();
        } catch (PeriodCloseBlocked $ex) {
            $this->assertSame(['C05'], array_map(fn ($i) => $i->code, $ex->items));
        }
        $this->assertSame(PeriodStatus::OPEN, $this->dec($e)->fresh()->status);
        $this->assertSame(0, PeriodTimeSummary::count());
    }

    public function test_close_recomputes_timecards_first(): void
    {
        $e = $this->profiled('2026-01-01');
        $this->setExpected($e, 25);
        Punch::factory()->create([
            'employment_id' => $e->id,
            'resolution' => PunchResolution::RESOLVED,
            'punched_at' => '2026-12-10 08:00:00',
        ]);
        Punch::factory()->create([
            'employment_id' => $e->id,
            'resolution' => PunchResolution::RESOLVED,
            'punched_at' => '2026-12-10 17:00:00',
        ]);

        $p = app(PeriodCloseService::class)->close($this->dec($e), $this->hr($e));
        $row = app(SummaryService::class)->final($p)->firstWhere('employment_id', $e->id);

        $this->assertSame('1.00', $row->worked_days);
    }

    public function test_employee_with_no_timecards_still_gets_a_row(): void
    {
        $e = $this->profiled('2026-01-01');
        $this->setExpected($e, 25);
        $p = app(PeriodCloseService::class)->close($this->dec($e), $this->hr($e));
        $row = app(SummaryService::class)->final($p)->sole();

        $this->assertSame(['0.00', '25.00'], [$row->worked_days, $row->nopay_days]);
    }

    public function test_reopen_and_close_again_creates_a_new_version(): void
    {
        $e = $this->clean($this->company());
        $close = app(PeriodCloseService::class);
        $p = $close->close($this->dec($e), $this->hr($e));
        $p = $close->reopen($p, $this->admin($e), 'Two missing punches found after closing');
        $this->assertSame(PeriodStatus::OPEN, $p->status);

        app(AdjustmentService::class)->addPunch($e, '2026-12-26 08:00:00', 'Device was offline', $this->hr($e));
        app(AdjustmentService::class)->addPunch($e, '2026-12-26 17:00:00', 'Device was offline', $this->hr($e));
        $p = $close->close($p, $this->hr($e));

        $this->assertSame(2, $p->close_version);
        $this->assertSame(2, PeriodTimeSummary::where('employment_id', $e->id)->count());
        $this->assertSame(['25.00', '26.00'], PeriodTimeSummary::where('employment_id', $e->id)->orderBy('version')->pluck('worked_days')->all());
        $this->assertSame('26.00', app(SummaryService::class)->final($p)->sole()->worked_days);
        $this->assertSame(['CLOSED', 'REOPENED', 'CLOSED'], PeriodEvent::orderBy('id')->pluck('event')->all());
    }

    public function test_reopen_needs_permission_and_a_reason(): void
    {
        $e = $this->clean($this->company());
        $close = app(PeriodCloseService::class);
        $p = $close->close($this->dec($e), $this->hr($e));

        try {
            $close->reopen($p, $this->hr($e), 'Two missing punches found after closing');
            $this->fail();
        } catch (AuthorizationException) {
            $this->assertSame(PeriodStatus::CLOSED, $p->fresh()->status);
        }

        try {
            $close->reopen($p, $this->admin($e), 'oops');
            $this->fail();
        } catch (InvalidPeriodAction) {
            $this->assertSame(PeriodStatus::CLOSED, $p->fresh()->status);
        }
    }

    public function test_close_needs_period_close_permission(): void
    {
        $e = $this->clean($this->company());
        $officer = User::factory()->forCompanies([$e->company])->withRole('hr-officer')->create(['must_change_password' => false]);

        try {
            app(PeriodCloseService::class)->close($this->dec($e), $officer);
            $this->fail();
        } catch (AuthorizationException) {
        }

        $this->assertSame(PeriodStatus::OPEN, $this->dec($e)->fresh()->status);
        $this->assertSame(0, PeriodTimeSummary::count());
    }

    public function test_current_returns_live_figures_when_open_and_stored_figures_when_closed(): void
    {
        $e = $this->profiled('2026-01-01');
        $this->setExpected($e, 25);
        $this->work($e, ['2026-12-01' => ['08:00', '17:00']]);
        $summaries = app(SummaryService::class);
        $period = $this->dec($e);

        $this->assertSame(1.0, $summaries->current($e, $period)->workedDays);
        $this->work($e, ['2026-12-02' => ['08:00', '17:00']]);
        $this->assertSame(2.0, $summaries->current($e->fresh(), $period->fresh())->workedDays);

        $closed = app(PeriodCloseService::class)->close($period->fresh(), $this->hr($e));
        $stored = $summaries->current($e->fresh(), $closed);
        DB::table('timecards')->where('employment_id', $e->id)->update(['day_value' => 0]);
        $again = $summaries->current($e->fresh(), $closed->fresh());

        $this->assertSame($stored->workedDays, $again->workedDays);
        $this->assertSame(2.0, $again->workedDays);
    }

    public function test_override_is_carried_into_the_stored_summary(): void
    {
        $e = $this->profiled('2026-01-01');
        $this->fullDays($e, '2026-12-01', 22);
        Carbon::setTestNow('2026-12-30 09:00:00');
        app(SummaryService::class)->setOverride($e, $this->dec($e), OverrideField::NOPAY_DAYS, 1, 'Two days were company shutdown', $this->hr($e));
        Carbon::setTestNow('2027-01-05 09:00:00');

        $p = app(PeriodCloseService::class)->close($this->dec($e), $this->hr($e));
        $row = app(SummaryService::class)->final($p)->sole();

        $this->assertTrue($row->has_override);
        $this->assertSame('1.00', $row->nopay_days);
    }

    public function test_summary_rows_cannot_be_updated_or_deleted(): void
    {
        $e = $this->clean($this->company());
        app(PeriodCloseService::class)->close($this->dec($e), $this->hr($e));
        $row = PeriodTimeSummary::query()->first();

        try {
            $row->update(['worked_days' => 1]);
            $this->fail();
        } catch (\LogicException) {
        }

        try {
            $row->delete();
            $this->fail();
        } catch (\LogicException) {
        }

        $this->assertSame(1, PeriodTimeSummary::count());
        $this->assertSame('25.00', $row->fresh()->worked_days);
    }
}
