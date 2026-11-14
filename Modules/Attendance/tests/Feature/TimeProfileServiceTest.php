<?php

namespace Modules\Attendance\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Attendance\Enums\AttendanceMode;
use Modules\Attendance\Enums\OtMode;
use Modules\Attendance\Exceptions\InvalidTimeProfile;
use Modules\Attendance\Models\EmploymentTimeProfile;
use Modules\Attendance\Services\ShiftService;
use Modules\Attendance\Services\TimeProfileService;
use Modules\Calendar\Services\PayPeriodService;
use Modules\CoreHr\Enums\ExitReason;
use Modules\CoreHr\Models\Employment;
use Modules\CoreHr\Readiness\ReadinessService;
use Modules\CoreHr\Services\EmploymentService;
use Tests\Support\HrFixtures;
use Tests\TestCase;

class TimeProfileServiceTest extends TestCase
{
    use HrFixtures;
    use RefreshDatabase;

    private TimeProfileService $svc;

    protected function setUp(): void
    {
        parent::setUp();

        $this->svc = app(TimeProfileService::class);
    }

    public function test_set_and_read_by_date(): void
    {
        $e = $this->hired('2026-01-01');
        $s1 = $this->shift();
        $s2 = $this->shift(['code' => 'S2']);
        $this->svc->set($e, ['effective_from' => '2026-01-01', 'shift_id' => $s1->id, 'ot_mode' => 'EVENING', 'late_deduction' => true]);
        $this->svc->set($e, ['effective_from' => '2026-07-01', 'shift_id' => $s2->id]);

        $june = $this->svc->on($e, '2026-06-30');
        $july = $this->svc->on($e, '2026-07-01');
        $this->assertSame([$s1->id, OtMode::EVENING, true], [$june->shift_id, $june->ot_mode, $june->late_deduction]);
        $this->assertSame([$s2->id, OtMode::EVENING, true], [$july->shift_id, $july->ot_mode, $july->late_deduction]);
        $this->assertSame('2026-06-30', $june->effective_to->toDateString());
        $this->assertNull($this->svc->on($e, '2025-12-31'));
    }

    public function test_defaults_for_a_first_profile(): void
    {
        $p = $this->svc->set($this->hired('2026-01-01'), ['effective_from' => '2026-01-01', 'shift_id' => $this->shift()->id]);
        $this->assertSame([AttendanceMode::PUNCH, OtMode::NONE, false], [$p->attendance_mode, $p->ot_mode, $p->late_deduction]);
    }

    public function test_no_punch_mode_switches_off_ot_and_late(): void
    {
        $p = $this->svc->set($this->hired('2026-01-01'), ['effective_from' => '2026-01-01', 'shift_id' => $this->shift()->id, 'attendance_mode' => 'NO_PUNCH', 'ot_mode' => 'BOTH', 'late_deduction' => true]);
        $this->assertSame([OtMode::NONE, false], [$p->ot_mode, $p->late_deduction]);
    }

    public function test_profile_before_hire_date_is_refused(): void
    {
        $this->expectException(InvalidTimeProfile::class);
        $this->svc->set($this->hired('2026-06-01'), ['effective_from' => '2026-05-31', 'shift_id' => $this->shift()->id]);
    }

    public function test_profile_in_a_closed_period_is_refused(): void
    {
        $this->travelTo('2027-01-05');
        $e = $this->hired('2026-01-01');
        $pp = app(PayPeriodService::class);
        $pp->markClosed($pp->ensure($e->company, 2026, 12), null);
        $this->expectException(InvalidTimeProfile::class);
        $this->svc->set($e, ['effective_from' => '2026-12-15', 'shift_id' => $this->shift()->id]);
    }

    public function test_inactive_shift_is_refused(): void
    {
        $shift = $this->shift();
        app(ShiftService::class)->deactivate($shift);
        $this->expectException(InvalidTimeProfile::class);
        $this->svc->set($this->hired('2026-01-01'), ['effective_from' => '2026-01-01', 'shift_id' => $shift->id]);
    }

    public function test_exit_closes_the_time_profile(): void
    {
        $e = $this->profiled('2026-01-01');
        app(EmploymentService::class)->exit($e, '2026-10-31', ExitReason::RESIGNATION);
        $profile = EmploymentTimeProfile::query()->where('employment_id', $e->id)->sole();
        $this->assertSame('2026-10-31', $profile->effective_to->toDateString());
    }

    public function test_set_many_reports_each_result(): void
    {
        $c = $this->company();
        $a = $this->hired('2026-01-01', $c);
        $b = $this->hired('2026-01-01', $c);
        $exited = $this->hired('2026-01-01', $c);
        app(EmploymentService::class)->exit($exited, '2026-06-01', ExitReason::RESIGNATION);

        $shift = $this->shift();
        $results = $this->svc->setMany(
            [$a->id, $b->id, $exited->id],
            ['effective_from' => '2026-01-01', 'shift_id' => $shift->id],
        );

        $this->assertInstanceOf(EmploymentTimeProfile::class, $results[$a->id]);
        $this->assertInstanceOf(EmploymentTimeProfile::class, $results[$b->id]);
        $this->assertIsString($results[$exited->id]);
        $this->assertStringContainsString('exited', strtolower($results[$exited->id]));
    }

    public function test_hr012_reports_employees_without_a_profile(): void
    {
        $c = $this->company();
        $with = $this->profiled('2026-01-01', $c);
        $without = $this->hired('2026-01-01', $c);
        $codes = fn (Employment $e) => app(ReadinessService::class)->forEmployment($e, '2026-11-01')->pluck('code')->all();
        $this->assertNotContains('HR012', $codes($with));
        $this->assertContains('HR012', $codes($without));
    }
}
