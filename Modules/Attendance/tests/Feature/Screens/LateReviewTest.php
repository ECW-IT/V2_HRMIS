<?php

namespace Modules\Attendance\Tests\Feature\Screens;

use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Attendance\Livewire\LateReview;
use Modules\Attendance\Models\Timecard;
use Tests\Support\HrFixtures;
use Tests\Support\PeriodSummaryHelpers;
use Tests\TestCase;

class LateReviewTest extends TestCase
{
    use HrFixtures;
    use PeriodSummaryHelpers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-12-10 09:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_chargeable_is_total_minus_grace(): void
    {
        $company = $this->company();
        $employment = $this->profiled('2026-01-01', $company, ['late_deduction' => true]);
        $days = [];
        for ($day = 1; $day <= 9; $day++) {
            $days[sprintf('2026-12-%02d', $day)] = ['08:36', '17:00'];
        }
        $this->work($employment, $days);
        $this->loginAs('hr-manager', $company);

        Livewire::test(LateReview::class)->assertSee('Chargeable late minutes: 39');
    }

    public function test_waive_updates_the_row(): void
    {
        $company = $this->company();
        $employment = $this->profiled('2026-01-01', $company, ['late_deduction' => true]);
        $days = [];
        for ($day = 1; $day <= 9; $day++) {
            $days[sprintf('2026-12-%02d', $day)] = ['08:36', '17:00'];
        }
        $this->work($employment, $days);
        $card = Timecard::query()->where('employment_id', $employment->id)->orderBy('work_date')->firstOrFail();
        $this->loginAs('hr-manager', $company);

        Livewire::test(LateReview::class)
            ->call('openWaive', $card->id)
            ->set('reason', 'Traffic holdup')
            ->call('saveWaive')
            ->assertSee('Waived')
            ->assertSee('Chargeable late minutes: 8');
    }
}
