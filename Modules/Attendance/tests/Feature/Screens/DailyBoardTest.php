<?php

namespace Modules\Attendance\Tests\Feature\Screens;

use App\Models\User;
use App\Support\Company\CompanyContext;
use Carbon\Carbon;
use Database\Seeders\ApprovalDefinitionSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Attendance\Enums\PunchResolution;
use Modules\Attendance\Livewire\DailyBoard;
use Modules\Attendance\Models\Punch;
use Modules\Attendance\Services\TimecardService;
use Modules\CoreHr\Models\Employment;
use Modules\Leave\Database\Seeders\LeaveSeeder;
use Modules\Leave\Enums\LeavePortion;
use Modules\Leave\Models\LeaveType;
use Modules\Leave\Services\LeaveService;
use Tests\Support\HrFixtures;
use Tests\TestCase;

class DailyBoardTest extends TestCase
{
    use HrFixtures;
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_board_needs_timecard_view(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $company = $this->company();
        $user = User::factory()->forCompanies([$company])->create(['must_change_password' => false]);
        $this->actingAs($user);

        $this->get(route('attendance.board'))->assertForbidden();
    }

    public function test_today_shows_in_for_one_punch_and_present_for_two(): void
    {
        Carbon::setTestNow('2026-12-10 09:00:00');
        $company = $this->company();
        $in = $this->profiled('2026-01-01', $company);
        $present = $this->profiled('2026-01-01', $company);
        $absent = $this->profiled('2026-01-01', $company);
        $this->punch($in, '2026-12-10 08:00:00');
        $this->punch($present, '2026-12-10 07:55:00');
        $this->punch($present, '2026-12-10 08:50:00');
        $this->loginAs('hr-manager', $company);

        $html = Livewire::test(DailyBoard::class)->html();

        $this->assertMatchesRegularExpression('/'.preg_quote($in->person->name_with_initials, '/').'.{0,400}In/s', $html);
        $this->assertMatchesRegularExpression('/'.preg_quote($present->person->name_with_initials, '/').'.{0,400}Present/s', $html);
        $this->assertMatchesRegularExpression('/'.preg_quote($absent->person->name_with_initials, '/').'.{0,400}Absent/s', $html);
    }

    public function test_past_date_reads_timecards_and_shows_missing_punch(): void
    {
        Carbon::setTestNow('2026-12-10 09:00:00');
        $company = $this->company();
        $employment = $this->profiled('2026-01-01', $company);
        $this->punch($employment, '2026-12-01 08:00:00');
        app(TimecardService::class)->compute($employment, '2026-12-01');
        $this->loginAs('hr-manager', $company);

        Livewire::test(DailyBoard::class)
            ->set('date', '2026-12-01')
            ->assertSee('Missing punch');
    }

    public function test_employee_on_approved_leave_shows_on_leave(): void
    {
        Carbon::setTestNow('2026-12-10 09:00:00');
        $this->seed(LeaveSeeder::class);
        $this->seed(ApprovalDefinitionSeeder::class);
        $company = $this->company();
        $employment = $this->profiled('2026-01-01', $company);
        $user = $this->loginAs('hr-manager', $company);
        $type = LeaveType::query()->where('code', 'ML')->firstOrFail();
        app(LeaveService::class)->request($employment, $type->id, '2026-12-10', LeavePortion::FULL, $user, ['pre_approved' => true]);

        Livewire::test(DailyBoard::class)->assertSee('On leave');
    }

    public function test_board_is_limited_to_the_selected_company_and_org_unit(): void
    {
        Carbon::setTestNow('2026-12-10 09:00:00');
        $a = $this->company();
        $b = $this->company();
        $home = $this->profiled('2026-01-01', $a);
        $otherUnit = $this->profiled('2026-01-01', $a);
        $otherCompany = $this->profiled('2026-01-01', $b);
        $this->loginAs('hr-manager', $a, $b);
        CompanyContext::set($a->id);

        Livewire::test(DailyBoard::class)
            ->set('orgUnitId', (string) $home->currentAssignment()->org_unit_id)
            ->assertSee($home->person->name_with_initials)
            ->assertDontSee($otherUnit->person->name_with_initials)
            ->assertDontSee($otherCompany->person->name_with_initials);
    }

    private function punch(Employment $employment, string $at): void
    {
        Punch::factory()->create([
            'employment_id' => $employment->id,
            'resolution' => PunchResolution::RESOLVED,
            'punched_at' => $at,
        ]);
    }
}
