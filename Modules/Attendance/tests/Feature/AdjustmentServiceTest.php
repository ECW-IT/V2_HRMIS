<?php

namespace Modules\Attendance\Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\ApprovalDefinitionSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Attendance\Enums\AdjustmentStatus;
use Modules\Attendance\Enums\DayType;
use Modules\Attendance\Enums\PunchResolution;
use Modules\Attendance\Enums\TimecardException;
use Modules\Attendance\Exceptions\InvalidAdjustment;
use Modules\Attendance\Models\AttendanceAdjustment;
use Modules\Attendance\Models\Punch;
use Modules\Attendance\Models\Timecard;
use Modules\Attendance\Services\AdjustmentService;
use Modules\Attendance\Services\TimecardService;
use Modules\Calendar\Services\PayPeriodService;
use Modules\CoreHr\Enums\ExitReason;
use Modules\CoreHr\Models\Employment;
use Modules\CoreHr\Services\EmploymentService;
use Modules\Organisation\Models\Company;
use Modules\Workflow\Enums\ApprovalStatus;
use Modules\Workflow\Services\ApprovalService;
use Tests\Support\HrFixtures;
use Tests\TestCase;

class AdjustmentServiceTest extends TestCase
{
    use HrFixtures;
    use RefreshDatabase;

    private AdjustmentService $svc;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-12-10 09:00:00');
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(ApprovalDefinitionSeeder::class);
        $this->svc = app(AdjustmentService::class);
    }

    private function manager(Employment $e): User
    {
        $user = User::factory()
            ->forCompanies([$e->company])
            ->withRole('hr-manager')
            ->withPermissions('approval.act')
            ->create(['must_change_password' => false]);

        $this->actingAs($user);

        return $user;
    }

    private function officer(Employment $e): User
    {
        $user = User::factory()
            ->forCompanies([$e->company])
            ->withRole('hr-officer')
            ->withPermissions('approval.act')
            ->create(['must_change_password' => false]);

        $this->actingAs($user);

        return $user;
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

    public function test_manager_adds_a_missing_out_punch_and_the_day_becomes_full(): void
    {
        $e = $this->profiled('2026-01-01');
        $this->punch($e, '2026-12-01 08:00:00');
        app(TimecardService::class)->compute($e, '2026-12-01');
        $this->assertSame(TimecardException::MISSING_PUNCH, $this->tc($e, '2026-12-01')->exception);

        $a = $this->svc->addPunch($e, '2026-12-01 17:05:00', 'Forgot to punch out', $this->manager($e));

        $this->assertSame(AdjustmentStatus::APPROVED, $a->status);
        $this->assertSame('2026-12-01', $a->work_date->toDateString());
        $t = $this->tc($e, '2026-12-01');
        $this->assertSame([DayType::FULL, null, 2, '17:05:00'], [$t->day_type, $t->exception, $t->punch_count, $t->last_out->format('H:i:s')]);
        $this->assertSame(1, Punch::count());
    }

    public function test_officer_request_waits_for_approval(): void
    {
        $e = $this->profiled('2026-01-01');
        $this->punch($e, '2026-12-01 08:00:00');
        app(TimecardService::class)->compute($e, '2026-12-01');

        $a = $this->svc->addPunch($e, '2026-12-01 17:05:00', 'Forgot to punch out', $this->officer($e));
        $this->assertSame(AdjustmentStatus::PENDING, $a->status);
        $this->assertSame(TimecardException::MISSING_PUNCH, $this->tc($e, '2026-12-01')->exception);

        app(ApprovalService::class)->approve($a->approvalRequest, $this->manager($e));
        $this->assertSame(AdjustmentStatus::APPROVED, $a->fresh()->status);
        $this->assertSame(DayType::FULL, $this->tc($e, '2026-12-01')->day_type);
    }

    public function test_rejected_request_changes_nothing(): void
    {
        $e = $this->profiled('2026-01-01');
        $this->punch($e, '2026-12-01 08:00:00');
        app(TimecardService::class)->compute($e, '2026-12-01');
        $a = $this->svc->addPunch($e, '2026-12-01 17:05:00', 'Forgot to punch out', $this->officer($e));
        app(ApprovalService::class)->reject($a->approvalRequest, $this->manager($e), 'CCTV shows he left at noon');
        $this->assertSame(AdjustmentStatus::REJECTED, $a->fresh()->status);
        $this->assertSame(1, $this->tc($e, '2026-12-01')->punch_count);
    }

    public function test_voiding_a_wrong_punch_removes_it_from_the_calculation_only(): void
    {
        $e = $this->profiled('2026-01-01');
        $this->punch($e, '2026-12-01 08:00:00');
        $this->punch($e, '2026-12-01 17:00:00');
        $wrong = $this->punch($e, '2026-12-01 23:40:00');
        $this->svc->voidPunch($wrong, 'Punched by security guard by mistake', $this->manager($e));

        $t = $this->tc($e, '2026-12-01');
        $this->assertSame(['17:00:00', 2], [$t->last_out->format('H:i:s'), $t->punch_count]);
        $this->assertSame(3, Punch::count());
        $this->assertNotNull($wrong->fresh());
    }

    public function test_added_punch_after_midnight_belongs_to_the_previous_work_date(): void
    {
        $e = $this->profiled('2026-01-01', profile: ['ot_mode' => 'EVENING']);
        $this->punch($e, '2026-12-01 08:00:00');
        $a = $this->svc->addPunch($e, '2026-12-02 00:28:00', 'Night job, device was off', $this->manager($e));
        $this->assertSame('2026-12-01', $a->work_date->toDateString());
        $this->assertSame(390, $this->tc($e, '2026-12-01')->ot_evening_minutes);
    }

    public function test_reason_is_required(): void
    {
        $e = $this->profiled('2026-01-01');
        foreach (['', 'ok'] as $bad) {
            try {
                $this->svc->addPunch($e, '2026-12-01 17:05:00', $bad, $this->manager($e));
                $this->fail();
            } catch (InvalidAdjustment) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_same_minute_cannot_be_added_twice(): void
    {
        $e = $this->profiled('2026-01-01');
        $this->punch($e, '2026-12-01 17:05:20');
        try {
            $this->svc->addPunch($e, '2026-12-01 17:05:00', 'Forgot to punch out', $this->manager($e));
            $this->fail();
        } catch (InvalidAdjustment) {
        }
        $this->svc->addPunch($e, '2026-12-01 17:20:00', 'Forgot to punch out', $this->officer($e));
        $this->expectException(InvalidAdjustment::class);
        $this->svc->addPunch($e, '2026-12-01 17:20:30', 'Forgot to punch out', $this->manager($e));
    }

    public function test_a_punch_cannot_be_voided_twice(): void
    {
        $e = $this->profiled('2026-01-01');
        $p = $this->punch($e, '2026-12-01 08:00:00');
        $this->svc->voidPunch($p, 'Wrong punch by guard', $this->manager($e));
        $this->expectException(InvalidAdjustment::class);
        $this->svc->voidPunch($p, 'Wrong punch by guard again', $this->manager($e));
    }

    public function test_future_time_and_dates_outside_the_employment_are_refused(): void
    {
        $e = $this->profiled('2026-12-05');
        $manager = $this->manager($e);

        foreach ([
            '2026-12-10 09:00:01',
            '2026-12-04 17:05:00',
        ] as $bad) {
            try {
                $this->svc->addPunch($e, $bad, 'Forgot to punch out', $manager);
                $this->fail();
            } catch (InvalidAdjustment) {
                $this->addToAssertionCount(1);
            }
        }

        app(EmploymentService::class)->exit($e, '2026-12-08', ExitReason::RESIGNATION);
        try {
            $this->svc->addPunch($e->fresh(), '2026-12-09 17:05:00', 'Forgot to punch out', $manager);
            $this->fail();
        } catch (InvalidAdjustment) {
            $this->addToAssertionCount(1);
        }
    }

    public function test_closed_period_cannot_be_adjusted(): void
    {
        Carbon::setTestNow('2027-01-05 09:00:00');
        $e = $this->profiled('2026-01-01');
        $pp = app(PayPeriodService::class);
        $pp->markClosed($pp->ensure($e->company, 2026, 12), null);
        $this->expectException(InvalidAdjustment::class);
        $this->svc->addPunch($e, '2026-12-01 17:05:00', 'Forgot to punch out', $this->manager($e));
    }

    public function test_user_without_permission_or_company_access_is_refused(): void
    {
        $e = $this->profiled('2026-01-01');
        $noPerm = User::factory()->forCompanies([$e->company])->create(['must_change_password' => false]);
        $otherCompany = Company::factory()->create();
        $outsider = User::factory()
            ->forCompanies([$otherCompany])
            ->withRole('hr-manager')
            ->create(['must_change_password' => false]);

        try {
            $this->svc->addPunch($e, '2026-12-01 17:05:00', 'Forgot to punch out', $noPerm);
            $this->fail();
        } catch (AuthorizationException) {
            $this->addToAssertionCount(1);
        }

        try {
            $this->svc->addPunch($e, '2026-12-01 17:05:00', 'Forgot to punch out', $outsider);
            $this->fail();
        } catch (AuthorizationException) {
            $this->addToAssertionCount(1);
        }
    }

    public function test_cancelling_an_approved_adjustment_recomputes_the_day(): void
    {
        $e = $this->profiled('2026-01-01');
        $this->punch($e, '2026-12-01 08:00:00');
        app(TimecardService::class)->compute($e, '2026-12-01');
        $manager = $this->manager($e);
        $a = $this->svc->addPunch($e, '2026-12-01 17:05:00', 'Forgot to punch out', $manager);
        $this->assertSame(DayType::FULL, $this->tc($e, '2026-12-01')->day_type);

        $cancelled = $this->svc->cancel($a, $manager);
        $this->assertSame(AdjustmentStatus::CANCELLED, $cancelled->status);
        $this->assertSame(TimecardException::MISSING_PUNCH, $this->tc($e, '2026-12-01')->exception);
    }

    public function test_requester_can_cancel_a_pending_request(): void
    {
        $e = $this->profiled('2026-01-01');
        $officer = $this->officer($e);
        $a = $this->svc->addPunch($e, '2026-12-01 17:05:00', 'Forgot to punch out', $officer);

        $cancelled = $this->svc->cancel($a, $officer);
        $this->assertSame(AdjustmentStatus::CANCELLED, $cancelled->status);
        $this->assertSame(ApprovalStatus::CANCELLED, $a->approvalRequest->fresh()->status);
    }

    public function test_pending_count_for_a_period(): void
    {
        $e = $this->profiled('2026-01-01');
        $o = $this->officer($e);
        $this->svc->addPunch($e, '2026-12-01 17:05:00', 'Forgot to punch out', $o);
        $this->svc->addPunch($e, '2026-12-02 17:05:00', 'Forgot to punch out', $this->manager($e));
        $this->svc->addPunch($e, '2026-11-30 17:05:00', 'Forgot to punch out', $o);
        $this->assertSame(1, $this->svc->pendingCount(app(PayPeriodService::class)->forDate($e->company_id, '2026-12-01')));
    }

    public function test_adjustments_are_audited(): void
    {
        $e = $this->profiled('2026-01-01');
        $manager = $this->manager($e);
        $a = $this->svc->addPunch($e, '2026-12-01 17:05:00', 'Forgot to punch out', $manager);

        $log = AuditLog::query()
            ->where('auditable_type', AttendanceAdjustment::class)
            ->where('auditable_id', $a->id)
            ->where('event', 'CREATED')
            ->sole();

        $this->assertSame($manager->id, $log->user_id);
        $this->assertSame('Forgot to punch out', $log->reason);
    }
}
