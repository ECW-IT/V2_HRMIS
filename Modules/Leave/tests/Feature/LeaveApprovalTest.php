<?php

namespace Modules\Leave\Tests\Feature;

use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\ApprovalDefinitionSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Attendance\Models\Timecard;
use Modules\Attendance\Services\TimecardService;
use Modules\Calendar\Services\PayPeriodService;
use Modules\CoreHr\Services\EmploymentService;
use Modules\Leave\Database\Seeders\LeaveSeeder;
use Modules\Leave\Enums\LeavePortion;
use Modules\Leave\Enums\LeaveStatus;
use Modules\Leave\Exceptions\InvalidLeaveRequest;
use Modules\Leave\Models\LeaveLedgerEntry;
use Modules\Leave\Models\LeaveType;
use Modules\Leave\Services\LeaveBalanceService;
use Modules\Leave\Services\LeaveService;
use Modules\Workflow\Services\ApprovalService;
use Tests\Support\HrFixtures;
use Tests\TestCase;

class LeaveApprovalTest extends TestCase
{
    use HrFixtures;
    use RefreshDatabase;

    private int $ml;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-12-10 09:00:00');
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(ApprovalDefinitionSeeder::class);
        $this->seed(LeaveSeeder::class);
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

    public function test_manager_then_hr_approval_takes_the_days_and_marks_the_timecard(): void
    {
        $c = $this->company();
        $boss = $this->hired('2026-01-01', $c);
        $e = $this->profiled('2026-01-01', $c);
        app(EmploymentService::class)->changeAssignment($e, '2026-02-01', ['manager_employment_id' => $boss->id], 'Reporting line');
        $bossUser = User::factory()->forCompanies([$c])->withRole('line-manager')->create(['person_id' => $boss->person_id]);
        app(TimecardService::class)->compute($e, '2026-12-08');

        $r = app(LeaveService::class)->request($e, $this->ml, '2026-12-08', LeavePortion::FULL, $this->officer($e));
        $approvals = app(ApprovalService::class);

        $approvals->approve($r->approvalRequest, $bossUser);
        $this->assertSame(LeaveStatus::PENDING, $r->fresh()->status);

        $approvals->approve($r->approvalRequest->fresh(), $this->hr($e));
        $this->assertSame(LeaveStatus::APPROVED, $r->fresh()->status);
        $this->assertSame(7.0, app(LeaveBalanceService::class)->balance($e, $this->ml, $this->period($e, '2026-12')));
        $t = Timecard::where('employment_id', $e->id)->whereDate('work_date', '2026-12-08')->sole();
        $this->assertSame([$this->ml, '1.00'], [$t->leave_type_id, $t->leave_days]);
    }

    public function test_rejection_takes_nothing(): void
    {
        $e = $this->hired('2026-01-01');
        $r = app(LeaveService::class)->request($e, $this->ml, '2026-12-08', LeavePortion::FULL, $this->officer($e));
        app(ApprovalService::class)->reject($r->approvalRequest->fresh(), $this->hr($e), 'Not enough cover');
        $this->assertSame(LeaveStatus::REJECTED, $r->fresh()->status);
        $this->assertSame(0, LeaveLedgerEntry::where('kind', 'TAKEN')->count());
        $this->assertSame(8.0, app(LeaveBalanceService::class)->balance($e, $this->ml, $this->period($e, '2026-12')));
    }

    public function test_approval_fails_if_the_balance_was_used_up_meanwhile(): void
    {
        LeaveType::whereKey($this->ml)->update(['enforce_balance' => false]);
        $e = $this->hired('2026-01-01');
        $o = $this->officer($e);
        $svc = app(LeaveService::class);
        $a = $svc->request($e, $this->ml, '2026-12-15', LeavePortion::FULL, $o);
        $b = $svc->request($e, $this->ml, '2026-12-16', LeavePortion::FULL, $o);
        LeaveType::whereKey($this->ml)->update(['enforce_balance' => true]);

        // Use up balance with pre-approved leaves except the pending ones still hold available down.
        // Simulate: approve first after reducing available via other approved days.
        app(LeaveBalanceService::class)->adjust($e, $this->ml, $this->period($e, '2026-12'), -7, 'Use up', $this->hr($e));

        app(ApprovalService::class)->approve($a->approvalRequest->fresh(), $this->hr($e));
        $this->assertSame(LeaveStatus::APPROVED, $a->fresh()->status);

        try {
            app(ApprovalService::class)->approve($b->approvalRequest->fresh(), $this->hr($e));
            $this->fail();
        } catch (InvalidLeaveRequest) {
            $this->addToAssertionCount(1);
        }
        $this->assertSame(LeaveStatus::PENDING, $b->fresh()->status);
    }

    public function test_cancelling_approved_leave_restores_the_balance_and_the_timecard(): void
    {
        $e = $this->profiled('2026-01-01');
        $hr = $this->hr($e);
        app(TimecardService::class)->compute($e, '2026-12-08');
        $r = app(LeaveService::class)->request($e, $this->ml, '2026-12-08', LeavePortion::FULL, $hr, ['pre_approved' => true]);

        app(LeaveService::class)->cancel($r, $hr);

        $this->assertSame(LeaveStatus::CANCELLED, $r->fresh()->status);
        $this->assertSame(8.0, app(LeaveBalanceService::class)->balance($e, $this->ml, $this->period($e, '2026-12')));
        $this->assertSame(['TAKEN', 'RESTORED'], LeaveLedgerEntry::whereNotNull('leave_request_id')->orderBy('id')->pluck('kind')->all());
        $t = Timecard::where('employment_id', $e->id)->whereDate('work_date', '2026-12-08')->sole();
        $this->assertSame([null, '0.00'], [$t->leave_type_id, $t->leave_days]);
    }

    public function test_officer_cannot_cancel_approved_leave_but_can_cancel_their_pending_request(): void
    {
        $e = $this->hired('2026-01-01');
        $o = $this->officer($e);
        $hr = $this->hr($e);
        $approved = app(LeaveService::class)->request($e, $this->ml, '2026-12-08', LeavePortion::FULL, $hr, ['pre_approved' => true]);
        try {
            app(LeaveService::class)->cancel($approved, $o);
            $this->fail();
        } catch (AuthorizationException) {
            $this->addToAssertionCount(1);
        }

        $pending = app(LeaveService::class)->request($e, $this->ml, '2026-12-09', LeavePortion::FULL, $o);
        $cancelled = app(LeaveService::class)->cancel($pending, $o);
        $this->assertSame(LeaveStatus::CANCELLED, $cancelled->status);
    }

    public function test_two_half_days_on_one_date_show_as_one_day_on_the_timecard(): void
    {
        $e = $this->profiled('2026-01-01');
        $hr = $this->hr($e);
        app(TimecardService::class)->compute($e, '2026-12-08');
        app(LeaveService::class)->request($e, $this->ml, '2026-12-08', LeavePortion::MORNING, $hr, ['pre_approved' => true]);
        app(LeaveService::class)->request($e, $this->ml, '2026-12-08', LeavePortion::EVENING, $hr, ['pre_approved' => true]);
        $t = Timecard::where('employment_id', $e->id)->whereDate('work_date', '2026-12-08')->sole();
        $this->assertSame([$this->ml, '1.00'], [$t->leave_type_id, $t->leave_days]);
    }

    public function test_timecard_created_after_the_leave_was_approved_carries_the_leave(): void
    {
        $e = $this->profiled('2026-01-01');
        app(LeaveService::class)->request($e, $this->ml, '2026-12-09', LeavePortion::FULL, $this->hr($e), ['pre_approved' => true]);
        $t = app(TimecardService::class)->compute($e, '2026-12-09');
        $this->assertSame('1.00', $t->leave_days);
    }

    public function test_leave_cannot_be_cancelled_in_a_closed_period(): void
    {
        $e = $this->hired('2026-01-01');
        $hr = $this->hr($e);
        $r = app(LeaveService::class)->request($e, $this->ml, '2026-12-08', LeavePortion::FULL, $hr, ['pre_approved' => true]);
        Carbon::setTestNow('2027-01-05 09:00:00');
        app(PayPeriodService::class)->markClosed(app(PayPeriodService::class)->forDate($e->company_id, '2026-12-08'), null);
        $this->expectException(InvalidLeaveRequest::class);
        app(LeaveService::class)->cancel($r, $hr);
    }
}
