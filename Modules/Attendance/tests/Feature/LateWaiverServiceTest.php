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
use Modules\Attendance\Models\TimecardDecisionLog;
use Modules\Attendance\Services\LateWaiverService;
use Modules\Attendance\Services\TimecardService;
use Modules\Calendar\Services\PayPeriodService;
use Modules\CoreHr\Models\Employment;
use Tests\Support\HrFixtures;
use Tests\TestCase;

class LateWaiverServiceTest extends TestCase
{
    use HrFixtures;
    use RefreshDatabase;

    private LateWaiverService $svc;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-12-10 09:00:00');
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->svc = app(LateWaiverService::class);
    }

    private function punch(Employment $e, string $at): Punch
    {
        return Punch::factory()->create([
            'employment_id' => $e->id,
            'resolution' => PunchResolution::RESOLVED,
            'punched_at' => $at,
        ]);
    }

    private function approver(Employment $e): User
    {
        return User::factory()
            ->forCompanies([$e->company])
            ->withRole('hr-manager')
            ->create(['must_change_password' => false]);
    }

    public function test_waive_and_restore(): void
    {
        $e = $this->profiled('2026-01-01', profile: ['late_deduction' => true]);
        $this->punch($e, '2026-12-01 08:36:00');
        $this->punch($e, '2026-12-01 17:00:00');
        $t = app(TimecardService::class)->compute($e, '2026-12-01');
        $by = $this->approver($e);
        $this->assertSame(31, LateWaiverService::chargeable($t));

        $t = $this->svc->waive($t, $by, 'Company bus broke down');
        $this->assertTrue($t->late_waived);
        $this->assertSame(31, $t->late_in_minutes);
        $this->assertSame(0, LateWaiverService::chargeable($t));

        $this->assertSame(31, LateWaiverService::chargeable($this->svc->restore($t, $by)));
        $this->assertSame(['LATE_WAIVED', 'LATE_RESTORED'], TimecardDecisionLog::orderBy('id')->pluck('kind')->all());
    }

    public function test_waiver_survives_recomputation(): void
    {
        $e = $this->profiled('2026-01-01', profile: ['late_deduction' => true]);
        $this->punch($e, '2026-12-01 08:36:00');
        $this->punch($e, '2026-12-01 17:00:00');
        $t = app(TimecardService::class)->compute($e, '2026-12-01');
        $by = $this->approver($e);
        $this->svc->waive($t, $by, 'Company bus broke down');

        $this->punch($e, '2026-12-01 17:30:00');
        $again = app(TimecardService::class)->compute($e, '2026-12-01');

        $this->assertTrue($again->late_waived);
        $this->assertSame(31, $again->late_in_minutes);
    }

    public function test_day_without_late_minutes_cannot_be_waived(): void
    {
        $e = $this->profiled('2026-01-01', profile: ['late_deduction' => true]);
        $this->punch($e, '2026-12-01 08:00:00');
        $this->punch($e, '2026-12-01 17:00:00');
        $t = app(TimecardService::class)->compute($e, '2026-12-01');

        $this->expectException(InvalidOtDecision::class);
        $this->svc->waive($t, $this->approver($e), 'Company bus broke down');
    }

    public function test_waiver_needs_a_reason_permission_and_an_open_period(): void
    {
        $e = $this->profiled('2026-01-01', profile: ['late_deduction' => true]);
        $this->punch($e, '2026-12-01 08:36:00');
        $this->punch($e, '2026-12-01 17:00:00');
        $t = app(TimecardService::class)->compute($e, '2026-12-01');
        $by = $this->approver($e);

        try {
            $this->svc->waive($t, $by, 'ok');
            $this->fail();
        } catch (InvalidOtDecision) {
            $this->addToAssertionCount(1);
        }

        $officer = User::factory()
            ->forCompanies([$e->company])
            ->withRole('hr-officer')
            ->create(['must_change_password' => false]);
        try {
            $this->svc->waive($t, $officer, 'Company bus broke down');
            $this->fail();
        } catch (AuthorizationException) {
            $this->addToAssertionCount(1);
        }

        Carbon::setTestNow('2027-01-05 09:00:00');
        $pp = app(PayPeriodService::class);
        $pp->markClosed($pp->forDate($e->company_id, '2026-12-01'), null);
        $this->expectException(InvalidOtDecision::class);
        $this->svc->waive($t->fresh(), $by, 'Company bus broke down');
    }
}
