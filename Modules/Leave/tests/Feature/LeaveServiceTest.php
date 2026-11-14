<?php

namespace Modules\Leave\Tests\Feature;

use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\ApprovalDefinitionSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Calendar\Services\PayPeriodService;
use Modules\CoreHr\Enums\ExitReason;
use Modules\CoreHr\Services\EmploymentService;
use Modules\Leave\Database\Seeders\LeaveSeeder;
use Modules\Leave\Enums\LeavePortion;
use Modules\Leave\Enums\LeaveStatus;
use Modules\Leave\Exceptions\InsufficientLeaveBalance;
use Modules\Leave\Exceptions\InvalidLeaveRequest;
use Modules\Leave\Models\LeaveLedgerEntry;
use Modules\Leave\Models\LeavePolicy;
use Modules\Leave\Models\LeaveRequest;
use Modules\Leave\Models\LeaveType;
use Modules\Leave\Services\LeaveBalanceService;
use Modules\Leave\Services\LeaveService;
use Modules\Organisation\Models\Company;
use Tests\Support\HrFixtures;
use Tests\TestCase;

class LeaveServiceTest extends TestCase
{
    use HrFixtures;
    use RefreshDatabase;

    private LeaveService $svc;

    private int $ml;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-12-10 09:00:00');
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(ApprovalDefinitionSeeder::class);
        $this->seed(LeaveSeeder::class);
        $this->svc = app(LeaveService::class);
        $this->ml = (int) LeaveType::query()->where('code', 'ML')->value('id');
    }

    private function hr($e): User
    {
        return User::factory()->forCompanies([$e->company])->withRole('hr-manager')->withPermissions('approval.act')->create(['must_change_password' => false]);
    }

    private function officer($e): User
    {
        return User::factory()->forCompanies([$e->company])->withRole('hr-officer')->withPermissions('approval.act')->create(['must_change_password' => false]);
    }

    private function period($e, string $ym)
    {
        [$y, $m] = array_map('intval', explode('-', $ym));

        return app(PayPeriodService::class)->ensure($e->company, $y, $m);
    }

    public function test_request_is_pending_and_takes_nothing_from_the_ledger(): void
    {
        $e = $this->hired('2026-01-01');
        $r = $this->svc->request($e, $this->ml, '2026-12-15', LeavePortion::FULL, $this->officer($e), ['reason' => 'Family event']);
        $this->assertSame([LeaveStatus::PENDING, '1.0', '2026-12-15'], [$r->status, $r->days, $r->date->toDateString()]);
        $this->assertSame(0, LeaveLedgerEntry::where('kind', 'TAKEN')->count());
        $this->assertSame('LEAVE_REQUEST', $r->approvalRequest->definition->code);
        $this->assertSame($e->id, $r->approvalRequest->employment_id);
    }

    public function test_half_day_is_half_a_day(): void
    {
        $e = $this->hired('2026-01-01');
        $r = $this->svc->request($e, $this->ml, '2026-12-15', LeavePortion::EVENING, $this->officer($e));
        $this->assertSame('0.5', $r->days);
    }

    public function test_overlapping_requests_are_refused(): void
    {
        $e = $this->hired('2026-01-01');
        $o = $this->officer($e);
        $this->svc->request($e, $this->ml, '2026-12-15', LeavePortion::MORNING, $o);
        $this->assertNotNull($this->svc->request($e, $this->ml, '2026-12-15', LeavePortion::EVENING, $o));
        foreach ([LeavePortion::MORNING, LeavePortion::FULL] as $p) {
            try {
                $this->svc->request($e, $this->ml, '2026-12-15', $p, $o);
                $this->fail($p->value);
            } catch (InvalidLeaveRequest) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_request_beyond_the_balance_is_refused(): void
    {
        $e = $this->hired('2026-01-01');
        $o = $this->officer($e);
        $this->svc->requestRange($e, $this->ml, '2026-12-11', '2026-12-18', $o);
        try {
            $this->svc->request($e, $this->ml, '2026-12-21', LeavePortion::MORNING, $o);
            $this->fail();
        } catch (InsufficientLeaveBalance $ex) {
            $this->assertStringContainsString('0', $ex->getMessage());
        }
        $this->assertNotNull($this->svc->request($e, $this->ml, '2027-01-04', LeavePortion::FULL, $o));
    }

    public function test_balance_is_not_enforced_when_the_type_says_so(): void
    {
        LeaveType::whereKey($this->ml)->update(['enforce_balance' => false]);
        $e = $this->hired('2026-01-01');
        $this->assertCount(10, $this->svc->requestRange($e, $this->ml, '2026-12-11', '2026-12-20', $this->officer($e)));
    }

    public function test_range_is_all_or_nothing(): void
    {
        $e = $this->hired('2026-01-01');
        $o = $this->officer($e);
        $this->svc->request($e, $this->ml, '2026-12-13', LeavePortion::FULL, $o);
        try {
            $this->svc->requestRange($e, $this->ml, '2026-12-11', '2026-12-14', $o);
            $this->fail();
        } catch (InvalidLeaveRequest) {
        }
        $this->assertSame(1, LeaveRequest::count());
    }

    public function test_range_can_skip_dates(): void
    {
        $e = $this->hired('2026-01-01');
        $made = $this->svc->requestRange($e, $this->ml, '2026-12-11', '2026-12-14', $this->officer($e), [], ['2026-12-13']);
        $this->assertSame(['2026-12-11', '2026-12-12', '2026-12-14'], $made->map(fn ($r) => $r->date->toDateString())->all());
    }

    public function test_invalid_requests(): void
    {
        $e = $this->hired('2026-12-05');
        $o = $this->officer($e);
        $cases = [
            '2026-12-04',
        ];
        foreach ($cases as $bad) {
            try {
                $this->svc->request($e, $this->ml, $bad, LeavePortion::FULL, $o);
                $this->fail($bad);
            } catch (InvalidLeaveRequest) {
                $this->addToAssertionCount(1);
            }
        }

        app(EmploymentService::class)->exit($e, '2026-12-08', ExitReason::RESIGNATION);
        try {
            $this->svc->request($e->fresh(), $this->ml, '2026-12-09', LeavePortion::FULL, $o);
            $this->fail();
        } catch (InvalidLeaveRequest) {
            $this->addToAssertionCount(1);
        }

        $e2 = $this->hired('2026-01-01');
        $o2 = $this->officer($e2);
        try {
            $this->svc->request($e2, $this->ml, '2027-03-12', LeavePortion::FULL, $o2);
            $this->fail('91 days');
        } catch (InvalidLeaveRequest) {
            $this->addToAssertionCount(1);
        }

        LeaveType::whereKey($this->ml)->update(['allows_half_day' => false]);
        try {
            $this->svc->request($e2, $this->ml, '2026-12-15', LeavePortion::MORNING, $o2);
            $this->fail('half');
        } catch (InvalidLeaveRequest) {
            $this->addToAssertionCount(1);
        }
        LeaveType::whereKey($this->ml)->update(['allows_half_day' => true, 'is_active' => false]);
        try {
            $this->svc->request($e2, $this->ml, '2026-12-15', LeavePortion::FULL, $o2);
            $this->fail('inactive');
        } catch (InvalidLeaveRequest) {
            $this->addToAssertionCount(1);
        }
        LeaveType::whereKey($this->ml)->update(['is_active' => true]);

        try {
            $this->svc->request($e2, $this->ml, '2026-12-15', LeavePortion::FULL, $o2, ['covering_employment_id' => $e2->id]);
            $this->fail('self cover');
        } catch (InvalidLeaveRequest) {
            $this->addToAssertionCount(1);
        }

        $other = $this->hired('2026-01-01', $this->company());
        try {
            $this->svc->request($e2, $this->ml, '2026-12-15', LeavePortion::FULL, $o2, ['covering_employment_id' => $other->id]);
            $this->fail('other company');
        } catch (InvalidLeaveRequest) {
            $this->addToAssertionCount(1);
        }

        LeavePolicy::query()->delete();
        try {
            $this->svc->request($e2, $this->ml, '2026-12-15', LeavePortion::FULL, $o2);
            $this->fail('no policy');
        } catch (InvalidLeaveRequest) {
            $this->addToAssertionCount(1);
        }
    }

    public function test_request_in_a_closed_period_is_refused(): void
    {
        Carbon::setTestNow('2027-01-05 09:00:00');
        $e = $this->hired('2026-01-01');
        $pp = app(PayPeriodService::class);
        $pp->markClosed($pp->ensure($e->company, 2026, 12), null);
        $this->expectException(InvalidLeaveRequest::class);
        $this->svc->request($e, $this->ml, '2026-12-15', LeavePortion::FULL, $this->officer($e));
    }

    public function test_user_without_leave_request_or_company_access_is_refused(): void
    {
        $e = $this->hired('2026-01-01');
        $noPerm = User::factory()->forCompanies([$e->company])->create(['must_change_password' => false]);
        $outsider = User::factory()->forCompanies([Company::factory()->create()])->withRole('hr-officer')->create(['must_change_password' => false]);
        try {
            $this->svc->request($e, $this->ml, '2026-12-15', LeavePortion::FULL, $noPerm);
            $this->fail();
        } catch (AuthorizationException) {
            $this->addToAssertionCount(1);
        }
        try {
            $this->svc->request($e, $this->ml, '2026-12-15', LeavePortion::FULL, $outsider);
            $this->fail();
        } catch (AuthorizationException) {
            $this->addToAssertionCount(1);
        }
    }

    public function test_pre_approved_needs_leave_manage_and_is_approved_at_once(): void
    {
        $e = $this->hired('2026-01-01');
        try {
            $this->svc->request($e, $this->ml, '2026-12-15', LeavePortion::FULL, $this->officer($e), ['pre_approved' => true]);
            $this->fail();
        } catch (AuthorizationException) {
        }

        $r = $this->svc->request($e, $this->ml, '2026-12-15', LeavePortion::FULL, $this->hr($e), ['pre_approved' => true]);
        $this->assertSame(LeaveStatus::APPROVED, $r->status);
        $this->assertSame(7.0, app(LeaveBalanceService::class)->balance($e, $this->ml, $this->period($e, '2026-12')));
        $this->assertNull($r->approval_request_id);
    }

    public function test_approved_days_are_grouped_by_type(): void
    {
        $e = $this->hired('2026-01-01');
        $hr = $this->hr($e);
        $this->svc->request($e, $this->ml, '2026-12-01', LeavePortion::FULL, $hr, ['pre_approved' => true]);
        $this->svc->request($e, $this->ml, '2026-12-02', LeavePortion::MORNING, $hr, ['pre_approved' => true]);
        $this->svc->request($e, $this->ml, '2026-12-03', LeavePortion::FULL, $this->officer($e));
        $this->assertSame([$this->ml => 1.5], $this->svc->approvedDays($e->id, $this->period($e, '2026-12')));
        $this->assertSame(1, $this->svc->pendingCount($this->period($e, '2026-12')));
    }
}
