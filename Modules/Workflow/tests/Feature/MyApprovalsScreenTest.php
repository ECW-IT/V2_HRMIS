<?php

namespace Modules\Workflow\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Organisation\Models\Company;
use Modules\Workflow\Enums\ApprovalActionType;
use Modules\Workflow\Enums\ApproverType;
use Modules\Workflow\Livewire\MyApprovals;
use Modules\Workflow\Models\ApprovalDefinition;
use Modules\Workflow\Services\ApprovalService;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\Support\Probe\ProbeApprovalHandler;
use Tests\Support\Probe\ScopedProbe;
use Tests\TestCase;

class MyApprovalsScreenTest extends TestCase
{
    use RefreshDatabase;

    private Company $c;

    private ApprovalDefinition $definition;

    private ScopedProbe $probe;

    private ApprovalService $svc;

    protected function setUp(): void
    {
        parent::setUp();

        ProbeApprovalHandler::$calls = 0;
        ProbeApprovalHandler::$shouldFail = false;

        Permission::findOrCreate('approval.act');
        foreach (['hr-manager', 'hr-officer', 'super-admin'] as $role) {
            Role::findOrCreate($role);
        }

        $this->svc = app(ApprovalService::class);
        $this->c = Company::factory()->create();
        $this->definition = ApprovalDefinition::query()->create([
            'code' => 'PROBE',
            'name' => 'Probe',
            'is_active' => true,
        ]);
        $this->definition->steps()->create([
            'sequence' => 1,
            'approver_type' => ApproverType::ROLE,
            'approver_ref' => 'hr-manager',
        ]);
        ApprovalService::registerHandler('PROBE', ProbeApprovalHandler::class);
        $this->probe = ScopedProbe::factory()->create(['company_id' => $this->c->id, 'name' => 'A']);
    }

    private function user(string $role): User
    {
        return User::factory()
            ->forCompanies([$this->c])
            ->withRole($role)
            ->withPermissions('approval.act')
            ->create(['must_change_password' => false]);
    }

    public function test_screen_needs_permission(): void
    {
        $user = User::factory()->forCompanies([$this->c])->create(['must_change_password' => false]);

        $this->actingAs($user)->get(route('approvals.index'))->assertForbidden();
    }

    public function test_waiting_list_shows_only_requests_the_user_can_act_on(): void
    {
        $manager = $this->user('hr-manager');
        $officer = $this->user('hr-officer');
        $admin = $this->user('super-admin');

        // Request the manager can act on
        $this->svc->submit('PROBE', $this->c->id, ScopedProbe::class, $this->probe->id, ApprovalActionType::UPDATE, ['name' => 'B'], $officer);

        // Request for another role (admin step only)
        $otherDef = ApprovalDefinition::query()->create(['code' => 'PROBE_ADMIN', 'name' => 'Admin probe', 'is_active' => true]);
        $otherDef->steps()->create(['sequence' => 1, 'approver_type' => ApproverType::ROLE, 'approver_ref' => 'super-admin']);
        ApprovalService::registerHandler('PROBE_ADMIN', ProbeApprovalHandler::class);
        $this->svc->submit('PROBE_ADMIN', $this->c->id, ScopedProbe::class, $this->probe->id, ApprovalActionType::UPDATE, ['name' => 'C'], $officer);

        // Request submitted by the manager (cannot act on own)
        $this->svc->submit('PROBE', $this->c->id, ScopedProbe::class, $this->probe->id, ApprovalActionType::UPDATE, ['name' => 'D'], $manager);

        Livewire::actingAs($manager)
            ->test(MyApprovals::class)
            ->assertSee('Probe')
            ->assertDontSee('Admin probe')
            ->assertViewHas('waiting', fn ($waiting) => $waiting->count() === 1);
    }

    public function test_reject_without_remarks_shows_a_validation_error(): void
    {
        $manager = $this->user('hr-manager');
        $officer = $this->user('hr-officer');
        $req = $this->svc->submit('PROBE', $this->c->id, ScopedProbe::class, $this->probe->id, ApprovalActionType::UPDATE, ['name' => 'B'], $officer);

        Livewire::actingAs($manager)
            ->test(MyApprovals::class)
            ->call('open', $req->id)
            ->set('remarks', '')
            ->call('reject')
            ->assertHasErrors('remarks');
    }
}
