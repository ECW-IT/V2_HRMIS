<?php

namespace Modules\Workflow\Tests\Feature;

use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\ApprovalDefinitionSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\CoreHr\Enums\ExitReason;
use Modules\CoreHr\Models\Employment;
use Modules\CoreHr\Services\EmploymentService;
use Modules\Leave\Database\Seeders\LeaveSeeder;
use Modules\Leave\Enums\LeavePortion;
use Modules\Leave\Models\LeaveType;
use Modules\Leave\Services\LeaveService;
use Modules\Organisation\Models\OrgUnit;
use Modules\Workflow\Models\ApprovalRequest;
use Modules\Workflow\Services\ApprovalService;
use Tests\Support\HrFixtures;
use Tests\TestCase;

class ManagerApproverTest extends TestCase
{
    use HrFixtures;
    use RefreshDatabase;

    private ApprovalService $svc;

    private int $ml;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-12-10 09:00:00');
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(ApprovalDefinitionSeeder::class);
        $this->seed(LeaveSeeder::class);
        $this->svc = app(ApprovalService::class);
        $this->ml = (int) LeaveType::query()->where('code', 'ML')->value('id');
    }

    private function hr(Employment $e): User
    {
        return User::factory()->forCompanies([$e->company])->withRole('hr-manager')->withPermissions('approval.act')->create(['must_change_password' => false]);
    }

    private function officer(Employment $e): User
    {
        return User::factory()->forCompanies([$e->company])->withRole('hr-officer')->withPermissions('approval.act')->create(['must_change_password' => false]);
    }

    private function submitWithManagerStep(Employment $e, User $by): ApprovalRequest
    {
        $r = app(LeaveService::class)->request($e, $this->ml, '2026-12-15', LeavePortion::FULL, $by);

        return $r->approvalRequest->fresh();
    }

    public function test_assignment_manager_with_a_user_account_can_act(): void
    {
        $c = $this->company();
        $boss = $this->hired('2026-01-01', $c);
        $e = $this->hired('2026-01-01', $c);
        app(EmploymentService::class)->changeAssignment($e, '2026-02-01', ['manager_employment_id' => $boss->id], 'Reporting line');
        $bossUser = User::factory()->forCompanies([$c])->withRole('line-manager')->create(['person_id' => $boss->person_id]);
        $other = User::factory()->forCompanies([$c])->withRole('line-manager')->create();

        $req = $this->submitWithManagerStep($e, by: $this->officer($e));
        $this->assertSame(1, $req->current_step);
        $this->assertTrue($this->svc->canAct($req, $bossUser));
        $this->assertFalse($this->svc->canAct($req, $other));
        $this->assertFalse($this->svc->canAct($req, $this->hr($e)));
    }

    public function test_org_unit_head_is_used_when_there_is_no_manager(): void
    {
        $c = $this->company();
        $head = $this->hired('2026-01-01', $c);
        $e = $this->hired('2026-01-01', $c);
        $unit = OrgUnit::query()->find($e->currentAssignment()->org_unit_id);
        $unit->update(['head_employment_id' => $head->id]);
        $headUser = User::factory()->forCompanies([$c])->withRole('line-manager')->create(['person_id' => $head->person_id]);

        $req = $this->submitWithManagerStep($e, by: $this->officer($e));
        $this->assertTrue($this->svc->canAct($req, $headUser));
    }

    public function test_step_is_skipped_when_nobody_has_a_user_account(): void
    {
        $e = $this->hired('2026-01-01');
        $req = $this->submitWithManagerStep($e, by: $this->officer($e));
        $this->assertSame(2, $req->current_step);
        $skip = $req->actions()->sole();
        $this->assertSame([null, 1, 'No manager with a user account — step skipped'], [$skip->user_id, $skip->step, $skip->remarks]);
        $this->assertTrue($this->svc->canAct($req, $this->hr($e)));
    }

    public function test_manager_who_has_exited_is_not_an_approver(): void
    {
        $c = $this->company();
        $boss = $this->hired('2026-01-01', $c);
        $e = $this->hired('2026-01-01', $c);
        app(EmploymentService::class)->changeAssignment($e, '2026-02-01', ['manager_employment_id' => $boss->id], 'Reporting line');
        $bossUser = User::factory()->forCompanies([$c])->withRole('line-manager')->create(['person_id' => $boss->person_id]);
        app(EmploymentService::class)->exit($boss, '2026-12-01', ExitReason::RESIGNATION);

        $req = $this->submitWithManagerStep($e, by: $this->officer($e));
        $this->assertFalse($this->svc->canAct($req, $bossUser));
        $this->assertSame(2, $req->current_step);
    }

    public function test_employee_who_is_their_own_manager_is_skipped(): void
    {
        $e = $this->hired('2026-01-01');
        // Bypass EmploymentService validation so we can assert ApprovalService skips self-as-manager.
        $e->currentAssignment()->update(['manager_employment_id' => $e->id]);
        User::factory()->forCompanies([$e->company])->withRole('line-manager')->create(['person_id' => $e->person_id]);

        $req = $this->submitWithManagerStep($e, by: $this->officer($e));
        $this->assertSame(2, $req->current_step);
    }

    public function test_manager_cannot_approve_a_request_they_submitted(): void
    {
        $c = $this->company();
        $boss = $this->hired('2026-01-01', $c);
        $e = $this->hired('2026-01-01', $c);
        app(EmploymentService::class)->changeAssignment($e, '2026-02-01', ['manager_employment_id' => $boss->id], 'Reporting line');
        $bossUser = User::factory()->forCompanies([$c])->withRole('line-manager')->withPermissions('leave.request')->create(['person_id' => $boss->person_id]);

        $req = $this->submitWithManagerStep($e, by: $bossUser);
        $this->assertFalse($this->svc->canAct($req, $bossUser));
    }
}
