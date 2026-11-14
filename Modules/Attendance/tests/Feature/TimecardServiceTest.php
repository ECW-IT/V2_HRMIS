<?php

namespace Modules\Attendance\Tests\Feature;

use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Attendance\Enums\DayType;
use Modules\Attendance\Enums\PunchResolution;
use Modules\Attendance\Enums\TimecardException;
use Modules\Attendance\Exceptions\TimecardLocked;
use Modules\Attendance\Models\Punch;
use Modules\Attendance\Models\Timecard;
use Modules\Attendance\Services\DeviceService;
use Modules\Attendance\Services\EnrolmentService;
use Modules\Attendance\Services\PunchIngestService;
use Modules\Attendance\Services\TimecardService;
use Modules\Attendance\Services\TimeProfileService;
use Modules\Calendar\Enums\HolidayKind;
use Modules\Calendar\Services\HolidayService;
use Modules\Calendar\Services\PayPeriodService;
use Modules\CoreHr\Enums\ExitReason;
use Modules\CoreHr\Models\Employment;
use Modules\CoreHr\Services\EmploymentService;
use Modules\Organisation\Models\Location;
use Tests\Support\HrFixtures;
use Tests\TestCase;

class TimecardServiceTest extends TestCase
{
    use HrFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-12-10 09:00:00');
    }

    private function punch(Employment $e, string $at): Punch
    {
        return Punch::factory()->create([
            'employment_id' => $e->id,
            'resolution' => PunchResolution::RESOLVED,
            'punched_at' => $at,
        ]);
    }

    private function tc(Employment $e, string $date): ?Timecard
    {
        return Timecard::withoutGlobalScopes()
            ->where('employment_id', $e->id)
            ->whereDate('work_date', $date)
            ->first();
    }

    public function test_compute_stores_the_calculated_day(): void
    {
        $e = $this->profiled('2026-01-01', profile: ['ot_mode' => 'EVENING', 'late_deduction' => true]);
        $this->punch($e, '2026-12-01 08:10:00');
        $this->punch($e, '2026-12-01 18:40:00');

        $t = app(TimecardService::class)->compute($e, '2026-12-01');

        $this->assertSame([DayType::FULL, '1.00', 5, 90, 2, null], [$t->day_type, $t->day_value, $t->late_in_minutes, $t->ot_evening_minutes, $t->punch_count, $t->exception]);
        $this->assertSame('2026-12-01 08:10:00', $t->first_in->format('Y-m-d H:i:s'));
        $this->assertSame($e->company_id, $t->company_id);
        $this->assertSame([2026, 12], [$t->payPeriod->year, $t->payPeriod->month]);
        $this->assertNull($t->ot_approved_minutes);
    }

    public function test_day_without_punches_gets_an_absent_row(): void
    {
        $t = app(TimecardService::class)->compute($this->profiled('2026-01-01'), '2026-12-01');
        $this->assertSame([DayType::NONE, '0.00', 0, null], [$t->day_type, $t->day_value, $t->punch_count, $t->exception]);
    }

    public function test_punch_after_midnight_counts_for_the_previous_work_date(): void
    {
        $e = $this->profiled('2026-01-01', profile: ['ot_mode' => 'EVENING']);
        $this->punch($e, '2026-12-01 08:00:00');
        $this->punch($e, '2026-12-02 00:28:00');
        $svc = app(TimecardService::class);
        $this->assertSame(390, $svc->compute($e, '2026-12-01')->ot_evening_minutes);
        $this->assertSame(0, $svc->compute($e, '2026-12-02')->punch_count);
    }

    public function test_unresolved_and_other_employees_punches_are_not_used(): void
    {
        $e = $this->profiled('2026-01-01');
        $other = $this->profiled('2026-01-01');
        $this->punch($other, '2026-12-01 08:00:00');
        $this->punch($other, '2026-12-01 17:00:00');
        Punch::factory()->create(['employment_id' => null, 'resolution' => 'UNRESOLVED', 'punched_at' => '2026-12-01 08:00:00']);
        $this->assertSame(0, app(TimecardService::class)->compute($e, '2026-12-01')->punch_count);
    }

    public function test_holiday_is_flagged(): void
    {
        $e = $this->profiled('2026-01-01');
        app(HolidayService::class)->add($e->company, '2026-12-01', 'Test holiday', HolidayKind::COMPANY);
        $this->assertTrue(app(TimecardService::class)->compute($e, '2026-12-01')->is_holiday);
        $this->assertFalse(app(TimecardService::class)->compute($e, '2026-12-02')->is_holiday);
    }

    public function test_no_row_outside_the_employment_or_in_the_future(): void
    {
        $e = $this->profiled('2026-12-05');
        $svc = app(TimecardService::class);
        $this->assertNull($svc->compute($e, '2026-12-04'));
        $this->assertNull($svc->compute($e, '2026-12-11'));
        $this->assertNotNull($svc->compute($e, '2026-12-05'));
        app(EmploymentService::class)->exit($e, '2026-12-08', ExitReason::RESIGNATION);
        $this->assertNull($svc->compute($e->fresh(), '2026-12-09'));
    }

    public function test_punches_without_a_time_profile_raise_no_profile(): void
    {
        $e = $this->hired('2026-01-01');
        $this->punch($e, '2026-12-01 08:00:00');
        $this->punch($e, '2026-12-01 17:00:00');
        $t = app(TimecardService::class)->compute($e, '2026-12-01');
        $this->assertSame([TimecardException::NO_PROFILE, DayType::NONE, 2], [$t->exception, $t->day_type, $t->punch_count]);
        $this->assertNull(app(TimecardService::class)->compute($e, '2026-12-02'));
    }

    public function test_no_punch_employees_get_no_timecards(): void
    {
        $e = $this->profiled('2026-01-01', profile: ['attendance_mode' => 'NO_PUNCH']);
        $this->assertNull(app(TimecardService::class)->compute($e, '2026-12-01'));
    }

    public function test_the_profile_effective_on_the_work_date_is_used(): void
    {
        $e = $this->profiled('2026-01-01', profile: ['ot_mode' => 'NONE']);
        app(TimeProfileService::class)->set($e, [
            'effective_from' => '2026-12-02',
            'shift_id' => app(TimeProfileService::class)->on($e, '2026-12-01')->shift_id,
            'ot_mode' => 'EVENING',
        ]);
        foreach (['2026-12-01', '2026-12-02'] as $d) {
            $this->punch($e, "$d 08:00:00");
            $this->punch($e, "$d 19:00:00");
        }
        $svc = app(TimecardService::class);
        $this->assertSame(0, $svc->compute($e, '2026-12-01')->ot_evening_minutes);
        $this->assertSame(120, $svc->compute($e, '2026-12-02')->ot_evening_minutes);
    }

    public function test_recompute_updates_the_same_row_and_keeps_decisions(): void
    {
        $e = $this->profiled('2026-01-01', profile: ['late_deduction' => true]);
        $svc = app(TimecardService::class);
        $this->punch($e, '2026-12-01 08:10:00');
        $this->punch($e, '2026-12-01 17:00:00');
        $t = $svc->compute($e, '2026-12-01');
        $t->forceFill(['late_waived' => true, 'leave_days' => 0.5])->save();

        $this->punch($e, '2026-12-01 17:20:00');
        $again = $svc->compute($e, '2026-12-01');

        $this->assertSame($t->id, $again->id);
        $this->assertSame('17:20:00', $again->last_out->format('H:i:s'));
        $this->assertTrue($again->late_waived);
        $this->assertSame('0.50', $again->leave_days);
        $this->assertSame(1, Timecard::where('employment_id', $e->id)->count());
    }

    public function test_ot_approval_is_cleared_when_computed_ot_changes(): void
    {
        $e = $this->profiled('2026-01-01', profile: ['ot_mode' => 'EVENING']);
        $svc = app(TimecardService::class);
        $this->punch($e, '2026-12-01 08:00:00');
        $this->punch($e, '2026-12-01 18:00:00');
        $t = $svc->compute($e, '2026-12-01');
        $t->forceFill(['ot_approved_minutes' => 60, 'ot_decided_at' => now()])->save();

        $svc->compute($e, '2026-12-01');
        $this->assertSame(60, $this->tc($e, '2026-12-01')->ot_approved_minutes);

        $this->punch($e, '2026-12-01 19:00:00');
        $svc->compute($e, '2026-12-01');
        $this->assertSame(120, $this->tc($e, '2026-12-01')->ot_evening_minutes);
        $this->assertNull($this->tc($e, '2026-12-01')->ot_approved_minutes);
        $this->assertNull($this->tc($e, '2026-12-01')->ot_decided_at);
    }

    public function test_closed_period_is_locked(): void
    {
        Carbon::setTestNow('2027-01-05 09:00:00');
        $e = $this->profiled('2026-01-01');
        $svc = app(TimecardService::class);
        $before = $svc->compute($e, '2026-12-01');
        $pp = app(PayPeriodService::class);
        $pp->markClosed($pp->forDate($e->company_id, '2026-12-01'), null);

        $this->assertTrue($svc->isLocked($e->company_id, '2026-12-01'));
        $this->punch($e, '2026-12-01 08:00:00');
        $this->punch($e, '2026-12-01 17:00:00');
        try {
            $svc->compute($e, '2026-12-01');
            $this->fail();
        } catch (TimecardLocked) {
        }
        $this->assertSame(0, $before->fresh()->punch_count);
    }

    public function test_compute_range_fills_every_day_for_every_punch_employee(): void
    {
        $c = $this->company();
        $a = $this->profiled('2026-01-01', $c);
        $b = $this->profiled('2026-12-03', $c);
        $this->profiled('2026-01-01', $c, ['attendance_mode' => 'NO_PUNCH']);
        $this->profiled('2026-01-01', $this->company());

        $written = app(TimecardService::class)->computeRange($c->id, '2026-12-01', '2026-12-05');

        $this->assertSame(8, $written);
        $this->assertSame(5, Timecard::where('employment_id', $a->id)->count());
        $this->assertSame(3, Timecard::where('employment_id', $b->id)->count());
    }

    public function test_compute_range_stops_at_today(): void
    {
        $c = $this->company();
        $this->profiled('2026-01-01', $c);
        $this->assertSame(2, app(TimecardService::class)->computeRange($c->id, '2026-12-09', '2026-12-31'));
    }

    public function test_ingesting_punches_recomputes_the_affected_days(): void
    {
        $e = $this->profiled('2026-01-01');
        app(EnrolmentService::class)->enrol($e, 'NIT', 116, '2026-01-01');
        [$device] = app(DeviceService::class)->register([
            'serial_no' => 'T1',
            'name' => 'T1',
            'location_id' => Location::factory()->create()->id,
            'device_group' => 'NIT',
        ]);

        app(PunchIngestService::class)->ingest($device, [
            ['device_user_id' => 116, 'punched_at' => '2026-12-01 08:00:00'],
            ['device_user_id' => 116, 'punched_at' => '2026-12-01 17:05:00'],
        ]);

        $this->assertSame(DayType::FULL, $this->tc($e, '2026-12-01')->day_type);
    }

    public function test_enrolling_later_resolves_punches_and_computes_those_days(): void
    {
        $e = $this->profiled('2026-01-01');
        [$device] = app(DeviceService::class)->register([
            'serial_no' => 'T1',
            'name' => 'T1',
            'location_id' => Location::factory()->create()->id,
            'device_group' => 'NIT',
        ]);
        app(PunchIngestService::class)->ingest($device, [
            ['device_user_id' => 999, 'punched_at' => '2026-12-01 08:00:00'],
            ['device_user_id' => 999, 'punched_at' => '2026-12-01 17:05:00'],
        ]);
        $this->assertNull($this->tc($e, '2026-12-01'));

        app(EnrolmentService::class)->enrol($e, 'NIT', 999, '2026-12-01');
        $this->assertSame(DayType::FULL, $this->tc($e, '2026-12-01')->day_type);
    }

    public function test_changing_a_time_profile_recomputes_from_its_start(): void
    {
        $e = $this->profiled('2026-01-01', profile: ['ot_mode' => 'NONE']);
        $this->punch($e, '2026-12-08 08:00:00');
        $this->punch($e, '2026-12-08 19:00:00');
        app(TimecardService::class)->compute($e, '2026-12-08');
        app(TimeProfileService::class)->set($e, [
            'effective_from' => '2026-12-08',
            'shift_id' => app(TimeProfileService::class)->on($e, '2026-12-01')->shift_id,
            'ot_mode' => 'EVENING',
        ]);
        $this->assertSame(120, $this->tc($e, '2026-12-08')->ot_evening_minutes);
    }

    public function test_one_failing_employee_does_not_stop_the_range(): void
    {
        Log::spy();
        $c = $this->company();
        $healthy = $this->profiled('2026-01-01', $c);
        $broken = $this->profiled('2026-01-01', $c);
        $profile = app(TimeProfileService::class)->on($broken, '2026-12-01');
        $shiftId = $profile->shift_id;

        DB::table('employment_time_profiles')->where('id', $profile->id)->update(['shift_id' => $shiftId]);
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::table('shifts')->where('id', $shiftId)->delete();
        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        $written = app(TimecardService::class)->computeRange($c->id, '2026-12-01', '2026-12-01');

        $this->assertSame(1, $written);
        $this->assertNotNull($this->tc($healthy, '2026-12-01'));
        Log::shouldHaveReceived('error')->atLeast()->once();
    }
}
