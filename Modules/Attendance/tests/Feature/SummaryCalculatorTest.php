<?php

namespace Modules\Attendance\Tests\Feature;

use Carbon\Carbon;
use Database\Seeders\ApprovalDefinitionSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Attendance\Enums\AttendanceMode;
use Modules\Attendance\Models\Timecard;
use Modules\Attendance\Services\LateWaiverService;
use Modules\Attendance\Services\OtApprovalService;
use Modules\Attendance\Services\SummaryService;
use Modules\Attendance\Summaries\SummaryCalculator;
use Modules\Calendar\Services\ExpectedDaysService;
use Modules\Calendar\Services\PayPeriodService;
use Modules\CoreHr\Enums\ExitReason;
use Modules\CoreHr\Services\EmploymentService;
use Modules\Leave\Database\Seeders\LeaveSeeder;
use Modules\Leave\Enums\LeavePortion;
use Modules\Organisation\Models\Grade;
use Tests\Support\HrFixtures;
use Tests\Support\PeriodSummaryHelpers;
use Tests\TestCase;

class SummaryCalculatorTest extends TestCase
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

    public function test_worked_days_against_expected_gives_no_pay(): void
    {
        $e = $this->profiled('2026-01-01');
        $this->fullDays($e, '2026-12-01', 24);
        $this->work($e, ['2026-12-28' => ['08:00', '13:00']]);
        $f = $this->fig($e);
        $this->assertSame([24.5, 24, 1, 0, 25.0, 24.5, 0.0, 0.5, 31],
            [$f->workedDays, $f->fullDays, $f->halfDays, $f->shortDays, $f->expectedDays, $f->creditedDays, $f->extraDays, $f->nopayDays, $f->employedDays]);
    }

    public function test_working_more_than_expected_gives_extra_days(): void
    {
        $e = $this->profiled('2026-01-01');
        $this->fullDays($e, '2026-12-01', 27);
        $f = $this->fig($e);
        $this->assertSame([27.0, 2.0, 0.0], [$f->workedDays, $f->extraDays, $f->nopayDays]);
    }

    public function test_short_day_counts_a_quarter(): void
    {
        $e = $this->profiled('2026-01-01');
        $this->work($e, ['2026-12-01' => ['10:30', '14:00']]);
        $f = $this->fig($e);
        $this->assertSame([0.25, 1, 24.75], [$f->workedDays, $f->shortDays, $f->nopayDays]);
    }

    public function test_late_minutes_above_the_grace_are_chargeable(): void
    {
        $e = $this->profiled('2026-01-01', profile: ['late_deduction' => true]);
        foreach (range(1, 9) as $d) {
            $this->work($e, [sprintf('2026-12-%02d', $d) => ['08:36', '17:00']]);
        }
        $f = $this->fig($e);
        $this->assertSame([279, 39], [$f->lateMinutesTotal, $f->lateMinutesChargeable]);
    }

    public function test_waived_late_minutes_are_left_out(): void
    {
        $e = $this->profiled('2026-01-01', profile: ['late_deduction' => true]);
        foreach (range(1, 9) as $d) {
            $this->work($e, [sprintf('2026-12-%02d', $d) => ['08:36', '17:00']]);
        }
        $t = Timecard::where('employment_id', $e->id)->whereDate('work_date', '2026-12-01')->sole();
        Carbon::setTestNow('2026-12-20 09:00:00');
        app(LateWaiverService::class)->waive($t, $this->hr($e), 'Company bus broke down');
        Carbon::setTestNow('2027-01-05 09:00:00');
        $f = $this->fig($e);
        $this->assertSame([248, 8], [$f->lateMinutesTotal, $f->lateMinutesChargeable]);
    }

    public function test_late_minutes_within_the_grace_are_not_chargeable(): void
    {
        $e = $this->profiled('2026-01-01', profile: ['late_deduction' => true]);
        foreach (range(1, 5) as $d) {
            $this->work($e, [sprintf('2026-12-%02d', $d) => ['08:36', '17:00']]);
        }
        $f = $this->fig($e);
        $this->assertSame([155, 0], [$f->lateMinutesTotal, $f->lateMinutesChargeable]);
    }

    public function test_grace_comes_from_the_company_setting(): void
    {
        $e = $this->profiled('2026-01-01', profile: ['late_deduction' => true]);
        $e->company->update(['monthly_late_grace_minutes' => 100]);
        foreach (range(1, 9) as $d) {
            $this->work($e, [sprintf('2026-12-%02d', $d) => ['08:36', '17:00']]);
        }
        $f = $this->fig($e->fresh());
        $this->assertSame([279, 179], [$f->lateMinutesTotal, $f->lateMinutesChargeable]);
    }

    public function test_only_approved_ot_is_counted(): void
    {
        $e = $this->profiled('2026-01-01', profile: ['ot_mode' => 'EVENING']);
        $this->work($e, [
            '2026-12-01' => ['08:00', '19:00'],
            '2026-12-02' => ['08:00', '18:00'],
            '2026-12-03' => ['08:00', '20:00'],
            '2026-12-04' => ['08:00', '19:00'],
        ]);
        Carbon::setTestNow('2026-12-20 09:00:00');
        $ot = app(OtApprovalService::class);
        $hr = $this->hr($e);
        $tc = fn ($d) => Timecard::where('employment_id', $e->id)->whereDate('work_date', $d)->sole();
        $ot->approve($tc('2026-12-01'), null, $hr);
        $ot->approve($tc('2026-12-02'), 30, $hr, 'Half authorised');
        $ot->refuse($tc('2026-12-03'), $hr, 'Not authorised');
        Carbon::setTestNow('2027-01-05 09:00:00');
        $this->assertSame([150, 0], [$this->fig($e)->otNormalMinutes, $this->fig($e)->otDoubleMinutes]);
    }

    public function test_monthly_leave_is_recorded_but_not_credited(): void
    {
        $e = $this->profiled('2026-01-01');
        $this->fullDays($e, '2026-12-01', 22);
        $this->leave($e, 'ML', ['2026-12-23', '2026-12-24']);
        $f = $this->fig($e);
        $this->assertSame([22.0, 0.0, 2.0, 22.0, 3.0], [$f->workedDays, $f->paidLeaveDays, $f->unpaidLeaveDays, $f->creditedDays, $f->nopayDays]);
    }

    public function test_paid_leave_is_credited(): void
    {
        $e = $this->profiled('2026-01-01');
        $this->paidType('PL');
        $this->fullDays($e, '2026-12-01', 22);
        $this->leave($e, 'PL', ['2026-12-23', '2026-12-24']);
        $f = $this->fig($e);
        $this->assertSame([22.0, 2.0, 24.0, 1.0], [$f->workedDays, $f->paidLeaveDays, $f->creditedDays, $f->nopayDays]);
    }

    public function test_a_day_is_never_credited_more_than_once(): void
    {
        $e = $this->profiled('2026-01-01');
        $this->paidType('PL');
        $this->work($e, ['2026-12-01' => ['08:00', '17:00'], '2026-12-02' => ['08:00', '13:00']]);
        $this->leave($e, 'PL', ['2026-12-01']);
        $this->leave($e, 'PL', ['2026-12-02'], LeavePortion::EVENING);
        $f = $this->fig($e);
        $this->assertSame([1.5, 1.5, 2.0], [$f->workedDays, $f->paidLeaveDays, $f->creditedDays]);
    }

    public function test_no_punch_employee_is_credited_expected_days_less_unpaid_leave(): void
    {
        $e = $this->profiled('2026-01-01', profile: ['attendance_mode' => 'NO_PUNCH']);
        $this->leave($e, 'ML', ['2026-12-23', '2026-12-24']);
        $f = $this->fig($e);
        $this->assertSame([AttendanceMode::NO_PUNCH, 23.0, 23.0, 0.0, 2.0, 0, 0],
            [$f->attendanceMode, $f->workedDays, $f->creditedDays, $f->extraDays, $f->nopayDays, $f->lateMinutesTotal, $f->otNormalMinutes]);
    }

    public function test_joiner_is_not_prorated(): void
    {
        $e = $this->profiled('2026-12-16');
        $this->fullDays($e, '2026-12-16', 14);
        $f = $this->fig($e);
        $this->assertSame([16, 25.0, 14.0, 11.0], [$f->employedDays, $f->expectedDays, $f->workedDays, $f->nopayDays]);
    }

    public function test_leaver_is_included_for_the_days_employed(): void
    {
        $e = $this->profiled('2026-01-01');
        $this->fullDays($e, '2026-12-01', 10);
        Carbon::setTestNow('2026-12-12 09:00:00');
        app(EmploymentService::class)->exit($e, '2026-12-10', ExitReason::RESIGNATION);
        Carbon::setTestNow('2027-01-05 09:00:00');
        $f = $this->fig($e->fresh());
        $this->assertSame([10, 10.0], [$f->employedDays, $f->workedDays]);
        $this->assertTrue(app(SummaryService::class)->employmentsIn($this->dec($e))->contains('id', $e->id));
    }

    public function test_employees_outside_the_period_are_not_included(): void
    {
        $c = $this->company();
        $later = $this->profiled('2027-01-02', $c);
        $earlier = $this->profiled('2026-01-01', $c);
        app(EmploymentService::class)->exit($earlier, '2026-11-30', ExitReason::RESIGNATION);
        $other = $this->profiled('2026-01-01');
        $inside = $this->profiled('2026-01-01', $c);

        $ids = app(SummaryService::class)->employmentsIn($this->dec($inside))->pluck('id')->all();

        $this->assertContains($inside->id, $ids);
        $this->assertNotContains($later->id, $ids);
        $this->assertNotContains($earlier->fresh()->id, $ids);
        $this->assertNotContains($other->id, $ids);
    }

    public function test_grade_on_the_last_employed_day_decides_expected_days(): void
    {
        $e = $this->profiled('2026-01-01');
        $gradeA = Grade::query()->findOrFail($e->assignmentOn('2026-12-14')->grade_id);
        $gradeB = Grade::factory()->create(['code' => 'G-B', 'name' => 'Grade B']);
        $period = app(PayPeriodService::class)->ensure($e->company, 2026, 12);
        app(ExpectedDaysService::class)->set($period, $gradeA, 25);
        app(EmploymentService::class)->changeAssignment($e, '2026-12-15', ['grade_id' => $gradeB->id], 'Moved to grade B');
        app(ExpectedDaysService::class)->set($period, $gradeB, 22);

        $f = $this->fig($e->fresh());

        $this->assertSame([22.0, $gradeB->id], [$f->expectedDays, $f->gradeId]);
    }

    public function test_missing_expected_days_gives_zero_expected(): void
    {
        $e = $this->profiled('2026-01-01');
        $period = app(PayPeriodService::class)->ensure($e->company, 2026, 12);
        $f = app(SummaryCalculator::class)->for($e, $period);
        $this->assertSame(0.0, $f->expectedDays);
    }

    public function test_timecards_of_other_periods_are_ignored(): void
    {
        $e = $this->profiled('2026-01-01');
        $this->fullDays($e, '2026-11-01', 5);
        $this->fullDays($e, '2026-12-01', 2);
        $f = $this->fig($e);
        $this->assertSame(2.0, $f->workedDays);
    }
}
