<?php

namespace Modules\Leave\Tests\Feature\Screens;

use Carbon\Carbon;
use Database\Seeders\ApprovalDefinitionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Leave\Database\Seeders\LeaveSeeder;
use Modules\Leave\Enums\LeavePortion;
use Modules\Leave\Livewire\LeaveCalendar;
use Modules\Leave\Models\LeaveType;
use Modules\Leave\Services\LeaveService;
use Tests\Support\HrFixtures;
use Tests\TestCase;

class LeaveCalendarTest extends TestCase
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

    public function test_calendar_marks_approved_pending_and_half_days(): void
    {
        $company = $this->company();
        $employment = $this->profiled('2026-01-01', $company);
        $user = $this->loginAs('hr-manager', $company);
        $type = LeaveType::query()->where('code', 'ML')->firstOrFail();
        $leave = app(LeaveService::class);
        $leave->request($employment, $type->id, '2026-12-02', LeavePortion::FULL, $user, ['pre_approved' => true]);
        $leave->request($employment, $type->id, '2026-12-03', LeavePortion::FULL, $user);
        $leave->request($employment, $type->id, '2026-12-04', LeavePortion::MORNING, $user, ['pre_approved' => true]);

        Livewire::test(LeaveCalendar::class)
            ->set('month', '2026-12')
            ->set('orgUnitId', (string) $employment->currentAssignment()->org_unit_id)
            ->assertSee('Approved')
            ->assertSee('Pending')
            ->assertSee('Half day')
            ->assertSee($employment->person->name_with_initials);
    }

    public function test_calendar_is_limited_to_the_org_unit(): void
    {
        $company = $this->company();
        $inside = $this->profiled('2026-01-01', $company);
        $outside = $this->profiled('2026-01-01', $company);
        $user = $this->loginAs('hr-manager', $company);
        $type = LeaveType::query()->where('code', 'ML')->firstOrFail();
        $leave = app(LeaveService::class);
        $leave->request($inside, $type->id, '2026-12-02', LeavePortion::FULL, $user, ['pre_approved' => true]);
        $leave->request($outside, $type->id, '2026-12-02', LeavePortion::FULL, $user, ['pre_approved' => true]);

        Livewire::test(LeaveCalendar::class)
            ->set('month', '2026-12')
            ->set('orgUnitId', (string) $inside->currentAssignment()->org_unit_id)
            ->assertSee($inside->person->name_with_initials)
            ->assertDontSee($outside->person->name_with_initials);
    }
}
