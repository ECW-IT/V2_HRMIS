<?php

namespace Modules\Payroll\Tests\Feature;

use App\Models\User;
use Database\Seeders\ApprovalDefinitionSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\CoreHr\Models\Employment;
use Modules\Payroll\Enums\OtRateMode;
use Modules\Payroll\Models\EmployeeCompensation;
use Modules\Payroll\Services\CompensationRequestService;
use Modules\Payroll\Services\CompensationService;
use Modules\Workflow\Enums\ApprovalStatus;
use Modules\Workflow\Livewire\MyApprovals;
use Modules\Workflow\Services\ApprovalService;
use Tests\Support\HrFixtures;
use Tests\TestCase;

class CompensationRequestTest extends TestCase
{
    use HrFixtures;
    use RefreshDatabase;

    private CompensationRequestService $req;

    private CompensationService $comp;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(ApprovalDefinitionSeeder::class);
        $this->req = app(CompensationRequestService::class);
        $this->comp = app(CompensationService::class);
    }

    private function user(string $role, Employment $e, array $extraPermissions = []): User
    {
        return User::factory()
            ->forCompanies([$e->company_id])
            ->withRole($role)
            ->withPermissions('approval.act', ...$extraPermissions)
            ->create(['must_change_password' => false]);
    }

    private function paidEmployment(string $hireDate = '2026-01-01'): Employment
    {
        return $this->paid($hireDate);
    }

    public function test_user_with_apply_directly_still_waits_for_approval(): void
    {
        $e = $this->paidEmployment();
        $admin = $this->user('super-admin', $e);

        $res = $this->req->request($admin, 'SET_COMPENSATION', $e, [
            'from' => '2026-07-01',
            'basic_salary' => '65000.00',
            'ot_rate_mode' => OtRateMode::CALCULATED->value,
            'change_reason' => 'Increase',
        ]);

        $this->assertSame(ApprovalStatus::PENDING, $res->status);
        $this->assertSame('60000.00', $this->comp->on($e, '2026-07-01')->basic_salary);

        app(ApprovalService::class)->approve($res, $admin);

        $this->assertSame('65000.00', $this->comp->on($e, '2026-07-01')->basic_salary);
    }

    public function test_other_users_submit_and_the_change_applies_on_approval(): void
    {
        $e = $this->paidEmployment();
        $officer = $this->user('payroll-officer', $e);

        $res = $this->req->request($officer, 'SET_COMPENSATION', $e, [
            'from' => '2026-07-01',
            'basic_salary' => '65000.00',
            'ot_rate_mode' => OtRateMode::CALCULATED->value,
            'change_reason' => 'Increase',
        ]);

        $this->assertSame(ApprovalStatus::PENDING, $res->status);
        $this->assertNull($this->comp->on($e, '2026-07-01')?->approval_request_id);
        $this->assertSame('60000.00', $this->comp->on($e, '2026-07-01')->basic_salary);

        app(ApprovalService::class)->approve($res, $this->user('hr-manager', $e));

        $row = EmployeeCompensation::query()
            ->where('employment_id', $e->id)
            ->whereDate('effective_from', '2026-07-01')
            ->first();

        $this->assertNotNull($row);
        $this->assertSame($res->id, $row->approval_request_id);
        $this->assertSame('65000.00', $row->basic_salary);
    }

    public function test_nobody_changes_their_own_compensation(): void
    {
        $e = $this->paidEmployment();
        $self = User::factory()
            ->forCompanies([$e->company_id])
            ->withRole('super-admin')
            ->create([
                'must_change_password' => false,
                'person_id' => $e->person_id,
            ]);

        $this->expectException(AuthorizationException::class);
        $this->req->request($self, 'SET_COMPENSATION', $e, [
            'from' => '2026-07-01',
            'basic_salary' => '70000.00',
            'ot_rate_mode' => OtRateMode::CALCULATED->value,
        ]);
    }

    public function test_user_without_compensation_manage_is_refused(): void
    {
        $e = $this->paidEmployment();
        $officer = $this->user('hr-officer', $e);

        $this->expectException(AuthorizationException::class);
        $this->req->request($officer, 'SET_COMPENSATION', $e, [
            'from' => '2026-07-01',
            'basic_salary' => '65000.00',
            'ot_rate_mode' => OtRateMode::CALCULATED->value,
        ]);
    }

    public function test_approvals_screen_hides_the_payload_without_compensation_view(): void
    {
        $e = $this->paidEmployment();
        $viewer = User::factory()
            ->forCompanies([$e->company_id])
            ->withPermissions('approval.act', 'compensation.manage')
            ->create(['must_change_password' => false]);

        $res = $this->req->request($viewer, 'SET_COMPENSATION', $e, [
            'from' => '2026-07-01',
            'basic_salary' => '65000.00',
            'ot_rate_mode' => OtRateMode::CALCULATED->value,
            'change_reason' => 'Increase',
        ]);

        $this->assertFalse($viewer->can('compensation.view'));

        Livewire::actingAs($viewer)
            ->test(MyApprovals::class)
            ->set('tab', 'mine')
            ->call('open', $res->id)
            ->assertSee('Compensation change (hidden)')
            ->assertDontSee('65000.00');
    }

    public function test_change_component_waits_for_approval_for_apply_directly_users(): void
    {
        $e = $this->paid('2026-01-01', null, [], ['FUEL' => '5000.00']);
        $admin = $this->user('super-admin', $e);
        $fuel = $this->comp->componentsOn($e, '2026-01-01')->first(
            fn ($row) => $row->payComponent?->code === 'FUEL'
        );

        $res = $this->req->request($admin, 'CHANGE_COMPONENT', $e, [
            'from' => '2026-06-01',
            'amount' => '8000.00',
            'note' => 'Increase',
            'employee_component_id' => $fuel->id,
        ]);

        $this->assertSame(ApprovalStatus::PENDING, $res->status);
        $this->assertSame('5000.00', $this->comp->componentsOn($e, '2026-06-01')->sole()->amount);

        app(ApprovalService::class)->approve($res, $admin);

        $this->assertSame('8000.00', $this->comp->componentsOn($e, '2026-06-01')->sole()->amount);
        $this->assertSame('5000.00', $this->comp->componentsOn($e, '2026-05-31')->sole()->amount);
    }

    public function test_change_component_applies_on_approval(): void
    {
        $e = $this->paid('2026-01-01', null, [], ['FUEL' => '5000.00']);
        $officer = $this->user('payroll-officer', $e);
        $fuel = $this->comp->componentsOn($e, '2026-01-01')->first(
            fn ($row) => $row->payComponent?->code === 'FUEL'
        );

        $res = $this->req->request($officer, 'CHANGE_COMPONENT', $e, [
            'employee_component_id' => $fuel->id,
            'from' => '2026-06-01',
            'amount' => '8000.00',
        ]);

        $this->assertSame(ApprovalStatus::PENDING, $res->status);
        $this->assertSame('5000.00', $this->comp->componentsOn($e, '2026-06-01')->sole()->amount);

        app(ApprovalService::class)->approve($res, $this->user('hr-manager', $e));

        $this->assertSame('8000.00', $this->comp->componentsOn($e, '2026-06-01')->sole()->amount);
    }
}
