<?php

namespace Modules\Attendance\Tests\Feature\Screens;

use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Attendance\Enums\OtMode;
use Modules\Attendance\Enums\PunchResolution;
use Modules\Attendance\Livewire\EmployeeTimecards;
use Modules\Attendance\Models\Punch;
use Modules\Attendance\Services\TimecardService;
use Modules\Calendar\Services\PayPeriodService;
use Modules\CoreHr\Models\Employment;
use Tests\Support\HrFixtures;
use Tests\Support\PeriodSummaryHelpers;
use Tests\TestCase;

class EmployeeTimecardsTest extends TestCase
{
    use HrFixtures;
    use PeriodSummaryHelpers;
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_month_lists_every_date_with_its_figures(): void
    {
        Carbon::setTestNow('2026-12-10 09:00:00');
        $employment = $this->profiled('2026-01-01', null, ['late_deduction' => true]);
        $this->punch($employment, '2026-12-01 08:36:00');
        $this->punch($employment, '2026-12-01 17:00:00');
        app(TimecardService::class)->compute($employment, '2026-12-01');
        $this->loginAs('hr-manager', $employment->company);

        Livewire::test(EmployeeTimecards::class, ['employment' => $employment, 'month' => '2026-12'])
            ->assertSee('2026-12-01')
            ->assertSee('2026-12-31')
            ->assertSee('08:36')
            ->assertSee('Full')
            ->assertSee('31');
    }

    public function test_footer_shows_the_live_summary(): void
    {
        Carbon::setTestNow('2026-12-31 09:00:00');
        $employment = $this->profiled('2026-01-01');
        $this->dec($employment);
        $this->fullDays($employment, '2026-12-01', 22);
        $this->loginAs('hr-manager', $employment->company);

        Livewire::test(EmployeeTimecards::class, ['employment' => $employment, 'month' => '2026-12'])
            ->assertSee('22.00')
            ->assertSee('3.00');
    }

    public function test_add_punch_dialog_calls_the_service_and_refreshes(): void
    {
        Carbon::setTestNow('2026-12-10 09:00:00');
        $employment = $this->profiled('2026-01-01');
        $this->punch($employment, '2026-12-01 08:00:00');
        app(TimecardService::class)->compute($employment, '2026-12-01');
        $this->loginAs('hr-manager', $employment->company);

        Livewire::test(EmployeeTimecards::class, ['employment' => $employment, 'month' => '2026-12'])
            ->assertSee('Missing punch')
            ->call('openAdd', '2026-12-01')
            ->set('punchedAt', '2026-12-01 17:00')
            ->set('reason', 'Forgot the out punch')
            ->call('saveAdd')
            ->assertSee('Full');
    }

    public function test_actions_are_hidden_in_a_closed_period(): void
    {
        Carbon::setTestNow('2027-01-05 09:00:00');
        $employment = $this->profiled('2026-01-01');
        $user = $this->loginAs('hr-manager', $employment->company);
        $period = app(PayPeriodService::class)->ensure($employment->company, 2026, 12);
        app(PayPeriodService::class)->markClosed($period, $user);

        Livewire::test(EmployeeTimecards::class, ['employment' => $employment, 'month' => '2026-12'])
            ->assertDontSee('Add punch')
            ->assertSee('Closed (version 1)');
    }

    public function test_actions_follow_permissions(): void
    {
        Carbon::setTestNow('2026-12-10 09:00:00');
        $employment = $this->profiled('2026-01-01', null, [
            'late_deduction' => true,
            'ot_mode' => OtMode::EVENING,
        ]);
        $this->punch($employment, '2026-12-01 08:36:00');
        $this->punch($employment, '2026-12-01 19:00:00');
        app(TimecardService::class)->compute($employment, '2026-12-01');
        $this->loginAs('hr-officer', $employment->company);

        Livewire::test(EmployeeTimecards::class, ['employment' => $employment, 'month' => '2026-12'])
            ->assertSee('Add punch')
            ->assertDontSee('Approve OT')
            ->assertDontSee('Waive late');
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
