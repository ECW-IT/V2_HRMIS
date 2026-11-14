<?php

namespace Modules\Attendance\Tests\Feature\Screens;

use Carbon\Carbon;
use Database\Seeders\ApprovalDefinitionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Attendance\Enums\AdjustmentStatus;
use Modules\Attendance\Enums\PunchResolution;
use Modules\Attendance\Livewire\Exceptions;
use Modules\Attendance\Models\AttendanceAdjustment;
use Modules\Attendance\Models\Punch;
use Modules\Attendance\Models\Timecard;
use Modules\Attendance\Services\TimecardService;
use Modules\CoreHr\Models\Employment;
use Modules\Organisation\Models\Company;
use Tests\Support\HrFixtures;
use Tests\TestCase;

class ExceptionsTest extends TestCase
{
    use HrFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-12-10 09:00:00');
        $this->seed(ApprovalDefinitionSeeder::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_tabs_show_counts(): void
    {
        $company = $this->company();
        $employment = $this->profiled('2026-01-01', $company);
        $this->punch($employment, '2026-12-01 08:00:00');
        app(TimecardService::class)->compute($employment, '2026-12-01');
        $this->loginAs('hr-officer', $company);

        Livewire::test(Exceptions::class)
            ->assertSee('Missing punch (1)')
            ->assertSee('No time profile (0)')
            ->assertSee('Too little presence (0)')
            ->assertSee('Unidentified punches (0)')
            ->assertSee('Pending adjustments (0)');
    }

    public function test_inline_add_punch_prefills_shift_end_for_a_morning_punch(): void
    {
        $company = $this->company();
        $employment = $this->profiled('2026-01-01', $company);
        $this->punch($employment, '2026-12-01 08:02:00');
        app(TimecardService::class)->compute($employment, '2026-12-01');
        $card = Timecard::query()->where('employment_id', $employment->id)->whereDate('work_date', '2026-12-01')->firstOrFail();
        $this->loginAs('hr-officer', $company);

        Livewire::test(Exceptions::class)
            ->call('openAdd', $card->id)
            ->assertSet('punchedAt', '2026-12-01 17:00');
    }

    public function test_bulk_add_creates_one_adjustment_per_row(): void
    {
        $company = $this->company();
        $first = $this->missing($company);
        $second = $this->missing($company);
        $this->loginAs('hr-manager', $company);
        $ids = Timecard::query()->whereIn('employment_id', [$first->id, $second->id])->pluck('id')->all();

        Livewire::test(Exceptions::class)
            ->set('selected', $ids)
            ->set('bulkReason', 'Forgot to punch out')
            ->call('addSelected');

        $this->assertSame(2, AttendanceAdjustment::query()->count());
    }

    public function test_hr_officer_bulk_add_creates_pending_requests(): void
    {
        $company = $this->company();
        $first = $this->missing($company);
        $second = $this->missing($company);
        $this->loginAs('hr-officer', $company);
        $ids = Timecard::query()->whereIn('employment_id', [$first->id, $second->id])->pluck('id')->all();

        Livewire::test(Exceptions::class)
            ->set('selected', $ids)
            ->set('bulkReason', 'Forgot to punch out')
            ->call('addSelected');

        $this->assertSame(2, AttendanceAdjustment::query()->where('status', AdjustmentStatus::PENDING)->count());
    }

    private function missing(Company $company): Employment
    {
        $employment = $this->profiled('2026-01-01', $company);
        $this->punch($employment, '2026-12-01 08:00:00');
        app(TimecardService::class)->compute($employment, '2026-12-01');

        return $employment;
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
