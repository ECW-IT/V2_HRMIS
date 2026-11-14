<?php

namespace Modules\Workflow\Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Organisation\Models\Company;
use Modules\Workflow\Enums\ApprovalActionType;
use Modules\Workflow\Enums\ApprovalStatus;
use Modules\Workflow\Enums\ApproverType;
use Modules\Workflow\Exceptions\NotAllowedToAct;
use Modules\Workflow\Exceptions\RequestAlreadyDecided;
use Modules\Workflow\Models\ApprovalDefinition;
use Modules\Workflow\Models\ApprovalRequest;
use Modules\Workflow\Services\ApprovalService;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\Support\Probe\ProbeApprovalHandler;
use Tests\Support\Probe\ScopedProbe;
use Tests\TestCase;

class ApprovalServiceTest extends TestCase
{
    use RefreshDatabase;

    private ApprovalService $svc;

    private Company $c;

    private ScopedProbe $probe;

    private ApprovalDefinition $definition;

    protected function setUp(): void
    {
        parent::setUp();

        ProbeApprovalHandler::$calls = 0;
        ProbeApprovalHandler::$shouldFail = false;

        Permission::findOrCreate('approval.act');
        foreach (['hr-manager', 'hr-officer', 'super-admin', 'payroll-officer'] as $role) {
            Role::findOrCreate($role);
        }

        $this->svc = app(ApprovalService::class);
        $this->c = Company::factory()->create();
        $this->definition = ApprovalDefinition::query()->create([
            'code' => 'PROBE',
            'name' => 'Probe',
            'is_active' => true,
        ]);
        ApprovalService::registerHandler('PROBE', ProbeApprovalHandler::class);
        $this->probe = ScopedProbe::factory()->create(['company_id' => $this->c->id, 'name' => 'A']);
    }

    private function steps(array $steps): void
    {
        $this->definition->steps()->delete();
        foreach ($steps as $index => [$type, $ref]) {
            $this->definition->steps()->create([
                'sequence' => $index + 1,
                'approver_type' => ApproverType::from($type),
                'approver_ref' => $ref,
            ]);
        }
    }

    private function user(string $role): User
    {
        return User::factory()->forCompanies([$this->c])->withRole($role)->withPermissions('approval.act')->create();
    }

    private function submitBy(User $user): ApprovalRequest
    {
        return $this->svc->submit(
            'PROBE',
            $this->c->id,
            ScopedProbe::class,
            $this->probe->id,
            ApprovalActionType::UPDATE,
            ['name' => 'B'],
            $user,
        );
    }

    public function test_single_step_request_applies_on_approval(): void
    {
        $this->steps([['ROLE', 'hr-manager']]);
        $req = $this->svc->submit('PROBE', $this->c->id, ScopedProbe::class, $this->probe->id, ApprovalActionType::UPDATE, ['name' => 'B'], $this->user('hr-officer'));
        $this->assertSame(ApprovalStatus::PENDING, $req->status);
        $this->assertSame('A', $this->probe->fresh()->name);

        $req = $this->svc->approve($req, $this->user('hr-manager'));

        $this->assertSame(ApprovalStatus::APPROVED, $req->status);
        $this->assertNotNull($req->decided_at);
        $this->assertSame('B', $this->probe->fresh()->name);
        $this->assertSame(1, ProbeApprovalHandler::$calls);
    }

    public function test_requester_cannot_approve_own_request(): void
    {
        $this->steps([['ROLE', 'hr-manager']]);
        $manager = $this->user('hr-manager');
        $req = $this->submitBy($manager);

        $this->assertFalse($this->svc->canAct($req, $manager));
        $this->expectException(NotAllowedToAct::class);
        $this->svc->approve($req, $manager);
    }

    public function test_two_step_request_needs_both_steps_in_order(): void
    {
        $this->steps([['ROLE', 'hr-manager'], ['ROLE', 'super-admin']]);
        $req = $this->submitBy($this->user('hr-officer'));
        $admin = $this->user('super-admin');

        $this->assertFalse($this->svc->canAct($req, $admin));          // not their step yet

        $req = $this->svc->approve($req, $this->user('hr-manager'));
        $this->assertSame(ApprovalStatus::PENDING, $req->status);
        $this->assertSame(2, $req->current_step);
        $this->assertSame(0, ProbeApprovalHandler::$calls);

        $req = $this->svc->approve($req, $admin);
        $this->assertSame(ApprovalStatus::APPROVED, $req->status);
        $this->assertSame(1, ProbeApprovalHandler::$calls);
        $this->assertSame(2, $req->actions()->count());
    }

    public function test_user_without_the_step_role_cannot_act(): void
    {
        $this->steps([['ROLE', 'hr-manager']]);
        $req = $this->submitBy($this->user('hr-officer'));
        $this->expectException(NotAllowedToAct::class);
        $this->svc->approve($req, $this->user('payroll-officer'));
    }

    public function test_user_of_another_company_cannot_act(): void
    {
        $this->steps([['ROLE', 'hr-manager']]);
        $req = $this->submitBy($this->user('hr-officer'));
        $outsider = User::factory()->forCompanies([Company::factory()->create()])->withRole('hr-manager')->withPermissions('approval.act')->create();
        $this->assertFalse($this->svc->canAct($req, $outsider));
    }

    public function test_reject_requires_remarks_and_does_not_apply(): void
    {
        $this->steps([['ROLE', 'hr-manager']]);
        $req = $this->submitBy($this->user('hr-officer'));
        $manager = $this->user('hr-manager');

        try {
            $this->svc->reject($req, $manager, '');
            $this->fail();
        } catch (\InvalidArgumentException) {
        }

        $req = $this->svc->reject($req, $manager, 'Wrong department');
        $this->assertSame(ApprovalStatus::REJECTED, $req->status);
        $this->assertSame('A', $this->probe->fresh()->name);
        $this->assertSame(0, ProbeApprovalHandler::$calls);
    }

    public function test_decided_request_cannot_be_acted_on_again(): void
    {
        $this->steps([['ROLE', 'hr-manager']]);
        $req = $this->svc->approve($this->submitBy($this->user('hr-officer')), $this->user('hr-manager'));
        $this->expectException(RequestAlreadyDecided::class);
        $this->svc->approve($req, $this->user('hr-manager'));
    }

    public function test_failing_handler_leaves_request_pending_and_nothing_saved(): void
    {
        $this->steps([['ROLE', 'hr-manager']]);
        $req = $this->submitBy($this->user('hr-officer'));
        ProbeApprovalHandler::$shouldFail = true;

        try {
            $this->svc->approve($req, $this->user('hr-manager'));
            $this->fail();
        } catch (\RuntimeException $e) {
            $this->assertSame('boom', $e->getMessage());
        }

        $this->assertSame(ApprovalStatus::PENDING, $req->fresh()->status);
        $this->assertSame(0, $req->actions()->count());
        $this->assertSame('A', $this->probe->fresh()->name);
    }

    public function test_definition_with_no_steps_applies_immediately(): void
    {
        $this->steps([]);
        $req = $this->submitBy($this->user('hr-officer'));
        $this->assertSame(ApprovalStatus::APPROVED, $req->status);
        $this->assertSame('B', $this->probe->fresh()->name);
    }

    public function test_only_requester_can_cancel_and_only_while_pending(): void
    {
        $this->steps([['ROLE', 'hr-manager']]);
        $officer = $this->user('hr-officer');
        $req = $this->submitBy($officer);

        try {
            $this->svc->cancel($req, $this->user('hr-manager'));
            $this->fail();
        } catch (NotAllowedToAct) {
        }
        $this->assertSame(ApprovalStatus::CANCELLED, $this->svc->cancel($req, $officer)->status);
    }

    public function test_approved_change_is_audited_with_the_request_number(): void
    {
        $this->steps([['ROLE', 'hr-manager']]);
        $req = $this->svc->approve($this->submitBy($this->user('hr-officer')), $this->user('hr-manager'));
        $log = AuditLog::where('auditable_type', ScopedProbe::class)->where('event', 'UPDATED')->sole();
        $this->assertSame("Approved request #{$req->id}", $log->reason);
    }

    public function test_stale_copy_of_a_decided_request_cannot_be_approved_again(): void
    {
        $this->steps([['ROLE', 'hr-manager']]);
        $req = $this->submitBy($this->user('hr-officer'));
        $stale = ApprovalRequest::find($req->id);
        $this->assertSame(ApprovalStatus::PENDING, $stale->status);

        $this->svc->approve($req, $this->user('hr-manager'));

        try {
            $this->svc->approve($stale, $this->user('hr-manager'));
            $this->fail();
        } catch (RequestAlreadyDecided) {
        }

        $this->assertSame(1, ProbeApprovalHandler::$calls);
        $this->assertSame(1, $req->actions()->count());
    }

    public function test_stale_copy_of_a_decided_request_cannot_be_rejected_or_cancelled(): void
    {
        $this->steps([['ROLE', 'hr-manager']]);
        $officer = $this->user('hr-officer');
        $req = $this->submitBy($officer);
        $stale = ApprovalRequest::find($req->id);

        $this->svc->approve($req, $this->user('hr-manager'));

        try {
            $this->svc->reject($stale, $this->user('hr-manager'), 'Too late');
            $this->fail();
        } catch (RequestAlreadyDecided) {
        }

        try {
            $this->svc->cancel($stale, $officer);
            $this->fail();
        } catch (NotAllowedToAct) {
        }

        $this->assertSame(ApprovalStatus::APPROVED, $req->fresh()->status);
    }
}
