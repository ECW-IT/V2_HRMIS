<?php

namespace Modules\Attendance\Tests\Feature\Screens;

use Carbon\Carbon;
use Database\Seeders\ApprovalDefinitionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Maatwebsite\Excel\Facades\Excel;
use Modules\Attendance\Enums\AttendanceMode;
use Modules\Attendance\Exports\PeriodSummaryColumns;
use Modules\Attendance\Exports\PeriodSummaryExport;
use Modules\Attendance\Livewire\PeriodSummary;
use Modules\Attendance\Services\PeriodCloseService;
use Modules\Leave\Database\Seeders\LeaveSeeder;
use Tests\Support\HrFixtures;
use Tests\Support\PeriodSummaryHelpers;
use Tests\TestCase;

class PeriodSummaryScreenTest extends TestCase
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

    public function test_open_period_shows_live_figures_with_a_banner(): void
    {
        Carbon::setTestNow('2026-12-10 09:00:00');
        $employment = $this->profiled('2026-01-01');
        $this->dec($employment);
        $this->fullDays($employment, '2026-12-01', 1);
        $this->loginAs('hr-manager', $employment->company);

        Livewire::test(PeriodSummary::class)
            ->assertSee('Not closed — figures can still change.')
            ->assertSee('1.00');
    }

    public function test_closed_period_shows_the_stored_version(): void
    {
        Carbon::setTestNow('2027-01-05 09:00:00');
        $employment = $this->profiled('2026-01-01', null, ['attendance_mode' => AttendanceMode::NO_PUNCH]);
        $period = $this->dec($employment);
        $user = $this->loginAs('hr-manager', $employment->company);
        app(PeriodCloseService::class)->close($period, $user);

        Livewire::test(PeriodSummary::class)
            ->assertDontSee('Not closed — figures can still change.')
            ->assertSee('25.00');
    }

    public function test_override_is_highlighted(): void
    {
        Carbon::setTestNow('2026-12-10 09:00:00');
        $employment = $this->profiled('2026-01-01');
        $this->dec($employment);
        $this->loginAs('hr-manager', $employment->company);

        Livewire::test(PeriodSummary::class)
            ->call('openOverride', $employment->id)
            ->set('overrideValue', '1.25')
            ->set('overrideReason', 'Agreed with the manager')
            ->call('saveOverride')
            ->assertSee('1.25')
            ->assertSee('Was 25.00')
            ->assertSeeHtml('bg-amber-100');
    }

    public function test_export_has_the_same_columns_as_the_screen(): void
    {
        Carbon::setTestNow('2026-12-10 09:00:00');
        $employment = $this->profiled('2026-01-01');
        $this->dec($employment);
        $this->loginAs('hr-manager', $employment->company);
        Excel::fake();

        $component = Livewire::test(PeriodSummary::class)->call('export');
        foreach (PeriodSummaryColumns::headings() as $heading) {
            $component->assertSee($heading);
        }
        $component->assertSee('Total');

        Excel::assertDownloaded('period-summary.xlsx', function (PeriodSummaryExport $export) {
            $rows = $export->array();

            return $export->headings() === PeriodSummaryColumns::headings()
                && ($rows[array_key_last($rows)][0] ?? null) === 'Total';
        });
    }
}
