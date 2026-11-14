<?php

namespace Modules\Leave\Tests\Feature\Screens;

use Carbon\Carbon;
use Database\Seeders\ApprovalDefinitionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\CoreHr\Livewire\EmployeeProfile;
use Modules\Leave\Database\Seeders\LeaveSeeder;
use Modules\Leave\Livewire\LeaveRequests;
use Modules\Leave\Models\LeaveRequest;
use Modules\Leave\Models\LeaveType;
use Tests\Support\HrFixtures;
use Tests\TestCase;

class LeaveRequestsScreenTest extends TestCase
{
    use HrFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-12-01 09:00:00');
        $this->seed(LeaveSeeder::class);
        $this->seed(ApprovalDefinitionSeeder::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_new_leave_for_a_range_creates_one_request_per_ticked_date(): void
    {
        $employment = $this->profiled('2026-01-01');
        $type = LeaveType::query()->where('code', 'ML')->firstOrFail();
        $this->loginAs('hr-manager', $employment->company);

        Livewire::test(LeaveRequests::class)
            ->call('openForm')
            ->set('employmentId', (string) $employment->id)
            ->set('leaveTypeId', (string) $type->id)
            ->set('from', '2026-12-01')
            ->set('to', '2026-12-03')
            ->set('dates.2026-12-02', false)
            ->call('save');

        $this->assertSame(2, LeaveRequest::query()->count());
        $this->assertDatabaseMissing('leave_requests', ['date' => '2026-12-02']);
    }

    public function test_dialog_shows_the_balance_and_the_service_error(): void
    {
        $employment = $this->profiled('2026-01-01');
        $type = LeaveType::query()->where('code', 'ML')->firstOrFail();
        $this->loginAs('hr-manager', $employment->company);

        Livewire::test(LeaveRequests::class)
            ->call('openForm')
            ->set('employmentId', (string) $employment->id)
            ->set('leaveTypeId', (string) $type->id)
            ->set('from', '2026-12-01')
            ->set('to', '2026-12-09')
            ->call('save')
            ->assertSee('8');

        $this->assertSame(0, LeaveRequest::query()->count());
    }

    public function test_pre_approved_checkbox_needs_leave_manage(): void
    {
        $employment = $this->profiled('2026-01-01');
        $this->loginAs('hr-officer', $employment->company);

        Livewire::test(LeaveRequests::class)
            ->call('openForm')
            ->assertDontSee('Pre-approved');

        $this->loginAs('hr-manager', $employment->company);

        Livewire::test(LeaveRequests::class)
            ->call('openForm')
            ->assertSee('Pre-approved');
    }

    public function test_leave_tab_shows_the_ledger_and_balance(): void
    {
        $employment = $this->profiled('2026-01-01');
        $this->loginAs('hr-officer', $employment->company);

        Livewire::test(EmployeeProfile::class, ['employment' => $employment])
            ->set('tab', 'leave')
            ->assertSee('Granted')
            ->assertSee('Balance');
    }
}
