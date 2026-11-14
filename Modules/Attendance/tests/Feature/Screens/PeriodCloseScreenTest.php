<?php

namespace Modules\Attendance\Tests\Feature\Screens;

use Carbon\Carbon;
use Database\Seeders\ApprovalDefinitionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Attendance\Enums\AttendanceMode;
use Modules\Attendance\Enums\PunchResolution;
use Modules\Attendance\Livewire\PeriodClose;
use Modules\Attendance\Models\Punch;
use Modules\Attendance\Services\TimecardService;
use Modules\Leave\Database\Seeders\LeaveSeeder;
use Tests\Support\HrFixtures;
use Tests\Support\PeriodSummaryHelpers;
use Tests\TestCase;

class PeriodCloseScreenTest extends TestCase
{
    use HrFixtures;
    use PeriodSummaryHelpers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LeaveSeeder::class);
        $this->seed(ApprovalDefinitionSeeder::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_checklist_is_listed_with_counts_and_links(): void
    {
        Carbon::setTestNow('2026-12-10 09:00:00');
        $company = $this->company();
        $employment = $this->profiled('2026-01-01', $company);
        Punch::factory()->create([
            'employment_id' => $employment->id,
            'resolution' => PunchResolution::RESOLVED,
            'punched_at' => '2026-12-01 08:00:00',
        ]);
        app(TimecardService::class)->compute($employment, '2026-12-01');
        $this->loginAs('hr-manager', $company);

        $html = Livewire::test(PeriodClose::class)
            ->assertSee('No missing punches')
            ->assertSee('1')
            ->html();

        $this->assertStringContainsString(route('attendance.exceptions'), $html);
        $this->assertMatchesRegularExpression('/id="close-period"[^>]*disabled/', $html);
    }

    public function test_close_button_is_disabled_while_a_blocking_item_fails(): void
    {
        Carbon::setTestNow('2026-12-10 09:00:00');
        $company = $this->company();
        $employment = $this->profiled('2026-01-01', $company);
        Punch::factory()->create([
            'employment_id' => $employment->id,
            'resolution' => PunchResolution::RESOLVED,
            'punched_at' => '2026-12-01 08:00:00',
        ]);
        app(TimecardService::class)->compute($employment, '2026-12-01');
        $this->loginAs('hr-manager', $company);

        $html = Livewire::test(PeriodClose::class)->html();

        $this->assertMatchesRegularExpression('/id="close-period"[^>]*disabled/', $html);
    }

    public function test_close_then_reopen(): void
    {
        Carbon::setTestNow('2027-01-05 09:00:00');
        $company = $this->company();
        $employment = $this->profiled('2026-01-01', $company, ['attendance_mode' => AttendanceMode::NO_PUNCH]);
        $this->dec($employment);
        $manager = $this->loginAs('hr-manager', $company);

        Livewire::test(PeriodClose::class)
            ->call('askClose')
            ->assertSee('Closing locks December 2026 for '.$company->name)
            ->call('closePeriod')
            ->assertSee('CLOSED');

        $this->loginAs('super-admin', $company);

        Livewire::test(PeriodClose::class)
            ->set('reopenReason', 'Figures were incomplete')
            ->call('reopenPeriod')
            ->assertSee('CLOSED')
            ->assertSee('REOPENED');

        $this->assertNotNull($manager->id);
    }

    public function test_hr_officer_cannot_close(): void
    {
        $company = $this->company();
        $this->loginAs('hr-officer', $company);

        $this->get(route('attendance.period-close'))->assertForbidden();
    }
}
