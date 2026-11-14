<?php

namespace Modules\Attendance\Tests\Feature;

use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\ApprovalDefinitionSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Attendance\Enums\OverrideField;
use Modules\Attendance\Enums\PunchResolution;
use Modules\Attendance\Models\Punch;
use Modules\Attendance\Services\AdjustmentService;
use Modules\Attendance\Services\PeriodCloseService;
use Modules\Attendance\Services\SummaryService;
use Modules\Attendance\Services\TimecardService;
use Modules\Calendar\Services\PayPeriodService;
use Modules\Leave\Database\Seeders\LeaveSeeder;
use Modules\Leave\Enums\LeavePortion;
use Modules\Leave\Models\LeaveType;
use Modules\Leave\Services\LeaveService;
use Tests\Support\HrFixtures;
use Tests\Support\PeriodSummaryHelpers;
use Tests\TestCase;

class PeriodChecklistTest extends TestCase
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

    public function test_clean_period_has_every_item_ok(): void
    {
        $e = $this->clean($this->company());
        $items = app(PeriodCloseService::class)->checklist($this->dec($e));
        $this->assertSame(['C01', 'C02', 'C03', 'C04', 'C05', 'C06', 'C07', 'C08', 'C09', 'W01', 'W02'], array_map(fn ($i) => $i->code, $items));
        $this->assertSame([], array_filter($items, fn ($i) => ! $i->ok));
    }

    public function test_c01_blocks_until_the_period_has_ended(): void
    {
        $e = $this->clean($this->company());
        Carbon::setTestNow('2026-12-31 09:00:00');
        $item = $this->item($this->dec($e), 'C01');
        $this->assertSame([1, true, false], [$item->count, $item->blocking, $item->ok]);
    }

    public function test_c02_blocks_while_november_is_open(): void
    {
        $e = $this->clean($this->company());
        app(PayPeriodService::class)->ensure($e->company, 2026, 11);
        $item = $this->item($this->dec($e), 'C02');
        $this->assertSame([1, true], [$item->count, $item->blocking]);
    }

    public function test_c03_counts_grades_without_expected_days(): void
    {
        $e = $this->clean($this->company());
        $this->profiled('2026-01-01', $e->company);
        $item = $this->item($this->dec($e), 'C03');
        $this->assertSame(1, $item->count);
        $this->assertTrue($item->blocking);
    }

    public function test_c04_counts_employees_without_a_time_profile(): void
    {
        $c = $this->company();
        $e = $this->clean($c);
        $this->hired('2026-01-01', $c);
        $item = $this->item($this->dec($e), 'C04');
        $this->assertSame(1, $item->count);
    }

    public function test_c05_counts_missing_punch_days(): void
    {
        $e = $this->profiled('2026-01-01');
        $this->setExpected($e, 25);
        $this->punch($e, '2026-12-03 08:00:00');
        app(TimecardService::class)->compute($e, '2026-12-03');
        $item = $this->item($this->dec($e), 'C05');
        $this->assertSame(1, $item->count);
    }

    public function test_c06_counts_pending_adjustments(): void
    {
        $e = $this->clean($this->company());
        $officer = User::factory()->forCompanies([$e->company])->withRole('hr-officer')->create(['must_change_password' => false]);
        app(AdjustmentService::class)->addPunch($e, '2026-12-26 08:00:00', 'Device was offline', $officer);
        $item = $this->item($this->dec($e), 'C06');
        $this->assertSame(1, $item->count);
    }

    public function test_c07_counts_undecided_ot(): void
    {
        $e = $this->profiled('2026-01-01', profile: ['ot_mode' => 'EVENING']);
        $this->setExpected($e, 25);
        $this->work($e, ['2026-12-01' => ['08:00', '19:00']]);
        $item = $this->item($this->dec($e), 'C07');
        $this->assertSame(1, $item->count);
    }

    public function test_c08_counts_pending_leave(): void
    {
        $e = $this->clean($this->company());
        $officer = User::factory()->forCompanies([$e->company])->withRole('hr-officer')->create(['must_change_password' => false]);
        $typeId = (int) LeaveType::query()->where('code', 'ML')->value('id');
        app(LeaveService::class)->request($e, $typeId, '2026-12-28', LeavePortion::FULL, $officer);
        $item = $this->item($this->dec($e), 'C08');
        $this->assertSame(1, $item->count);
    }

    public function test_c09_counts_unresolved_punches_in_the_period(): void
    {
        $e = $this->clean($this->company());
        Punch::factory()->create(['resolution' => PunchResolution::UNRESOLVED, 'punched_at' => '2026-12-31 23:00:00', 'employment_id' => null]);
        $item = $this->item($this->dec($e), 'C09');
        $this->assertSame(1, $item->count);

        Punch::factory()->create(['resolution' => PunchResolution::UNRESOLVED, 'punched_at' => '2027-01-01 03:00:00', 'employment_id' => null]);
        $item = $this->item($this->dec($e), 'C09');
        $this->assertSame(2, $item->count);
    }

    public function test_w01_short_presence_is_a_warning_only(): void
    {
        $e = $this->profiled('2026-01-01');
        $this->setExpected($e, 25);
        $this->work($e, ['2026-12-01' => ['08:00', '10:00']]);
        $item = $this->item($this->dec($e), 'W01');
        $this->assertSame([1, false], [$item->count, $item->blocking]);
    }

    public function test_w02_counts_employees_with_overrides(): void
    {
        $e = $this->clean($this->company());
        Carbon::setTestNow('2026-12-30 09:00:00');
        app(SummaryService::class)->setOverride($e, $this->dec($e), OverrideField::NOPAY_DAYS, 1, 'Two days were company shutdown', $this->hr($e));
        Carbon::setTestNow('2027-01-05 09:00:00');
        $item = $this->item($this->dec($e), 'W02');
        $this->assertSame([1, false], [$item->count, $item->blocking]);
    }
}
