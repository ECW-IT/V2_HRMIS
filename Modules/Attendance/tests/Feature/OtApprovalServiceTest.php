<?php

namespace Modules\Attendance\Tests\Feature;

use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Attendance\Enums\PunchResolution;
use Modules\Attendance\Exceptions\InvalidOtDecision;
use Modules\Attendance\Models\Punch;
use Modules\Attendance\Models\Timecard;
use Modules\Attendance\Models\TimecardDecisionLog;
use Modules\Attendance\Services\OtApprovalService;
use Modules\Attendance\Services\TimecardService;
use Modules\Calendar\Services\PayPeriodService;
use Modules\CoreHr\Models\Employment;
use Tests\Support\HrFixtures;
use Tests\TestCase;

class OtApprovalServiceTest extends TestCase
{
    use HrFixtures;
    use RefreshDatabase;

    private OtApprovalService $svc;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-12-10 09:00:00');
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->svc = app(OtApprovalService::class);
    }

    private function evening(): Employment
    {
        return $this->profiled('2026-01-01', profile: ['ot_mode' => 'EVENING']);
    }

    private function punch(Employment $e, string $at): Punch
    {
        return Punch::factory()->create([
            'employment_id' => $e->id,
            'resolution' => PunchResolution::RESOLVED,
            'punched_at' => $at,
        ]);
    }

    private function otDay(Employment $e, string $date, string $out = '19:00:00'): Timecard
    {
        $this->punch($e, "{$date} 08:00:00");
        $this->punch($e, "{$date} {$out}");

        return app(TimecardService::class)->compute($e, $date);
    }

    private function approver(Employment $e): User
    {
        return User::factory()
            ->forCompanies([$e->company])
            ->withRole('hr-manager')
            ->create(['must_change_password' => false]);
    }

    public function test_approve_all_computed_ot(): void
    {
        $e = $this->evening();
        $t = $this->otDay($e, '2026-12-01');
        $by = $this->approver($e);
        $t = $this->svc->approve($t, null, $by);
        $this->assertSame([120, $by->id], [$t->ot_approved_minutes, $t->ot_decided_by]);
        $this->assertNotNull($t->ot_decided_at);
        $log = TimecardDecisionLog::sole();
        $this->assertSame(['OT_APPROVED', null, 120, $by->id], [$log->kind, $log->old_value, $log->new_value, $log->user_id]);
    }

    public function test_partial_approval_needs_a_reason_and_a_whole_block(): void
    {
        $e = $this->evening();
        $t = $this->otDay($e, '2026-12-01');
        $by = $this->approver($e);
        try {
            $this->svc->approve($t, 60, $by);
            $this->fail('no reason');
        } catch (InvalidOtDecision) {
        }
        try {
            $this->svc->approve($t, 45, $by, 'Only one hour authorised');
            $this->fail('not a block');
        } catch (InvalidOtDecision) {
        }
        try {
            $this->svc->approve($t, 150, $by, 'x');
            $this->fail('above computed');
        } catch (InvalidOtDecision) {
        }
        $this->assertSame(60, $this->svc->approve($t, 60, $by, 'Only one hour authorised')->ot_approved_minutes);
    }

    public function test_refuse_sets_zero_and_needs_a_reason(): void
    {
        $e = $this->evening();
        $t = $this->otDay($e, '2026-12-01');
        $by = $this->approver($e);
        try {
            $this->svc->refuse($t, $by, '');
            $this->fail();
        } catch (InvalidOtDecision) {
        }
        $this->assertSame(0, $this->svc->refuse($t, $by, 'Not authorised by supervisor')->ot_approved_minutes);
    }

    public function test_reset_returns_to_undecided(): void
    {
        $e = $this->evening();
        $t = $this->svc->approve($this->otDay($e, '2026-12-01'), null, $this->approver($e));
        $this->assertNull($this->svc->reset($t, $this->approver($e))->ot_approved_minutes);
        $this->assertSame(['OT_APPROVED', 'OT_CLEARED'], TimecardDecisionLog::orderBy('id')->pluck('kind')->all());
    }

    public function test_day_without_computed_ot_or_with_an_exception_cannot_be_decided(): void
    {
        $e = $this->evening();
        $by = $this->approver($e);
        $none = $this->otDay($e, '2026-12-01', '17:10:00');
        try {
            $this->svc->approve($none, null, $by);
            $this->fail();
        } catch (InvalidOtDecision) {
        }
        $this->punch($e, '2026-12-02 08:00:00');
        $missing = app(TimecardService::class)->compute($e, '2026-12-02');
        $this->expectException(InvalidOtDecision::class);
        $this->svc->approve($missing, null, $by);
    }

    public function test_nobody_decides_their_own_ot(): void
    {
        $e = $this->evening();
        $t = $this->otDay($e, '2026-12-01');
        $self = User::factory()->forCompanies([$e->company])->withRole('hr-manager')->create(['person_id' => $e->person_id]);
        $this->expectException(InvalidOtDecision::class);
        $this->svc->approve($t, null, $self);
    }

    public function test_user_without_permission_is_refused(): void
    {
        $e = $this->evening();
        $t = $this->otDay($e, '2026-12-01');
        $officer = User::factory()
            ->forCompanies([$e->company])
            ->withRole('hr-officer')
            ->create(['must_change_password' => false]);

        $this->expectException(AuthorizationException::class);
        $this->svc->approve($t, null, $officer);
    }

    public function test_approve_many_reports_what_was_skipped(): void
    {
        $e = $this->evening();
        $by = $this->approver($e);
        $a = $this->otDay($e, '2026-12-01');
        $b = $this->otDay($e, '2026-12-02', '17:10:00');
        $c = $this->otDay($e, '2026-12-03', '18:00:00');
        $res = $this->svc->approveMany([$a->id, $b->id, $c->id], $by);
        $this->assertSame(2, $res['approved']);
        $this->assertSame([$b->id], array_keys($res['skipped']));
        $this->assertSame([120, null, 60], [$a->fresh()->ot_approved_minutes, $b->fresh()->ot_approved_minutes, $c->fresh()->ot_approved_minutes]);
    }

    public function test_engine_clearing_an_approval_is_logged(): void
    {
        $e = $this->evening();
        $t = $this->svc->approve($this->otDay($e, '2026-12-01'), null, $this->approver($e));
        $this->punch($e, '2026-12-01 20:00:00');
        app(TimecardService::class)->compute($e, '2026-12-01');
        $this->assertNull($t->fresh()->ot_approved_minutes);
        $log = TimecardDecisionLog::orderByDesc('id')->first();
        $this->assertSame(['OT_CLEARED', 120, null, null, 'Computed OT changed'], [$log->kind, $log->old_value, $log->new_value, $log->user_id, $log->reason]);
    }

    public function test_undecided_count(): void
    {
        $e = $this->evening();
        $by = $this->approver($e);
        $this->otDay($e, '2026-12-01');
        $this->svc->approve($this->otDay($e, '2026-12-02'), null, $by);
        $this->svc->refuse($this->otDay($e, '2026-12-03'), $by, 'Not authorised');
        $this->otDay($e, '2026-12-04', '17:10:00');
        $this->assertSame(1, $this->svc->undecidedCount(app(PayPeriodService::class)->forDate($e->company_id, '2026-12-01')));
    }

    public function test_closed_period_cannot_be_decided(): void
    {
        Carbon::setTestNow('2027-01-05 09:00:00');
        $e = $this->evening();
        $t = $this->otDay($e, '2026-12-01');
        $pp = app(PayPeriodService::class);
        $pp->markClosed($pp->forDate($e->company_id, '2026-12-01'), null);

        $this->expectException(InvalidOtDecision::class);
        $this->svc->approve($t, null, $this->approver($e));
    }
}
