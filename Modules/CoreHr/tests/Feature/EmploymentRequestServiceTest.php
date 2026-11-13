<?php

namespace Modules\CoreHr\Tests\Feature;

use App\Models\User;
use Database\Seeders\ApprovalDefinitionSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\CoreHr\Enums\EmploymentStatus;
use Modules\CoreHr\Models\Employment;
use Modules\CoreHr\Services\EmploymentRequestService;
use Modules\Organisation\Models\Grade;
use Modules\Workflow\Enums\ApprovalStatus;
use Modules\Workflow\Services\ApprovalService;
use Tests\Support\HrFixtures;
use Tests\TestCase;

class EmploymentRequestServiceTest extends TestCase
{
    use HrFixtures;
    use RefreshDatabase;

    private EmploymentRequestService $req;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(ApprovalDefinitionSeeder::class);
        $this->req = app(EmploymentRequestService::class);
    }

    private function user(string $role, Employment $e, array $extraPermissions = []): User
    {
        $factory = User::factory()
            ->forCompanies([$e->company_id])
            ->withRole($role)
            ->withPermissions('approval.act', ...$extraPermissions);

        return $factory->create(['must_change_password' => false]);
    }

    public function test_hr_manager_change_is_applied_directly(): void
    {
        $e = $this->hired('2026-06-01');
        $g2 = Grade::factory()->create()->id;
        $res = $this->req->request($this->user('hr-manager', $e), 'CHANGE_ASSIGNMENT', $e, ['from' => '2026-09-01', 'changes' => ['grade_id' => $g2], 'reason' => 'Promotion']);
        $this->assertNull($res);
        $this->assertSame($g2, $e->assignmentOn('2026-09-01')->grade_id);
    }

    public function test_hr_officer_change_creates_a_pending_request_and_changes_nothing(): void
    {
        $e = $this->hired('2026-06-01');
        $g1 = $e->currentAssignment()->grade_id;
        $g2 = Grade::factory()->create()->id;
        $res = $this->req->request($this->user('hr-officer', $e), 'CHANGE_ASSIGNMENT', $e, ['from' => '2026-09-01', 'changes' => ['grade_id' => $g2], 'reason' => 'Promotion']);

        $this->assertSame(ApprovalStatus::PENDING, $res->status);
        $this->assertSame('CHANGE_ASSIGNMENT', $res->payload['operation']);
        $this->assertSame(1, $e->assignments()->count());
        $this->assertSame($g1, $e->assignmentOn('2026-09-01')->grade_id);
    }

    public function test_approval_applies_the_change_with_the_requested_date_and_links_the_event(): void
    {
        $e = $this->hired('2026-06-01');
        $g2 = Grade::factory()->create()->id;
        $res = $this->req->request($this->user('hr-officer', $e), 'CHANGE_ASSIGNMENT', $e, ['from' => '2026-09-01', 'changes' => ['grade_id' => $g2], 'reason' => 'Promotion']);

        app(ApprovalService::class)->approve($res, $this->user('hr-manager', $e));

        $this->assertSame($g2, $e->assignmentOn('2026-09-01')->grade_id);
        $this->assertSame($res->id, $e->events()->where('type', 'ASSIGNMENT_CHANGE')->sole()->approval_request_id);
    }

    public function test_exit_request_uses_the_exit_definition(): void
    {
        $e = $this->hired('2026-01-01');
        $res = $this->req->request($this->user('hr-officer', $e, ['employee.exit']), 'EXIT', $e, ['on' => '2026-10-31', 'reason' => 'RESIGNATION', 'remarks' => null]);
        $this->assertSame('EMPLOYEE_EXIT', $res->definition->code);
        $this->assertSame(EmploymentStatus::ACTIVE, $e->fresh()->status);
    }

    public function test_user_without_exit_permission_cannot_request_an_exit(): void
    {
        $e = $this->hired('2026-01-01');
        $this->expectException(AuthorizationException::class);
        $this->req->request($this->user('hr-officer', $e), 'EXIT', $e, ['on' => '2026-10-31', 'reason' => 'RESIGNATION']);
    }

    public function test_person_update_goes_through_the_same_path(): void
    {
        $e = $this->hired();
        $res = $this->req->request($this->user('hr-officer', $e), 'UPDATE_PERSON', $e, ['person' => ['calling_name' => 'Sunil']]);
        $this->assertNull($e->person->fresh()->calling_name);
        app(ApprovalService::class)->approve($res, $this->user('hr-manager', $e));
        $this->assertSame('Sunil', $e->person->fresh()->calling_name);
    }
}
