<?php

namespace Modules\Attendance\Tests\Feature\Screens;

use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Attendance\Enums\OtMode;
use Modules\Attendance\Livewire\OtApproval;
use Modules\Attendance\Models\Timecard;
use Modules\Attendance\Services\OtApprovalService;
use Tests\Support\HrFixtures;
use Tests\Support\PeriodSummaryHelpers;
use Tests\TestCase;

class OtApprovalTest extends TestCase
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

    public function test_default_list_is_undecided_ot_of_the_period(): void
    {
        $company = $this->company();
        $employment = $this->profiled('2026-01-01', $company, ['ot_mode' => OtMode::EVENING]);
        $this->work($employment, [
            '2026-12-01' => ['08:00', '19:00'],
            '2026-12-02' => ['08:00', '19:00'],
        ]);
        $decided = Timecard::query()->where('employment_id', $employment->id)->whereDate('work_date', '2026-12-02')->firstOrFail();
        $user = $this->loginAs('hr-manager', $company);
        app(OtApprovalService::class)->approve($decided, null, $user);

        Livewire::test(OtApproval::class)
            ->assertSee('2026-12-01')
            ->assertDontSee('2026-12-02')
            ->assertSee('title="Approve all"', false)
            ->assertSee('title="Approve part"', false)
            ->assertSee('title="Refuse"', false)
            ->assertSee('title="Reset"', false);
    }

    public function test_select_all_ticks_every_visible_row_then_clears_them(): void
    {
        $company = $this->company();
        $employment = $this->profiled('2026-01-01', $company, ['ot_mode' => OtMode::EVENING]);
        $this->work($employment, [
            '2026-12-01' => ['08:00', '19:00'],
            '2026-12-02' => ['08:00', '19:00'],
        ]);
        $this->loginAs('hr-manager', $company);

        $ids = Timecard::query()
            ->where('employment_id', $employment->id)
            ->orderBy('work_date')
            ->orderBy('employment_id')
            ->pluck('id')
            ->map(fn ($id) => (string) $id)
            ->all();

        Livewire::test(OtApproval::class)
            ->call('toggleAll')
            ->assertSet('selected', $ids)
            ->call('toggleAll')
            ->assertSet('selected', []);
    }

    public function test_refuse_selected_sets_ticked_rows_to_zero_and_leaves_the_other(): void
    {
        $company = $this->company();
        $employment = $this->profiled('2026-01-01', $company, ['ot_mode' => OtMode::EVENING]);
        $this->work($employment, [
            '2026-12-01' => ['08:00', '19:00'],
            '2026-12-02' => ['08:00', '19:00'],
        ]);
        $ticked = Timecard::query()->where('employment_id', $employment->id)->whereDate('work_date', '2026-12-01')->firstOrFail();
        $unticked = Timecard::query()->where('employment_id', $employment->id)->whereDate('work_date', '2026-12-02')->firstOrFail();
        $this->loginAs('hr-manager', $company);

        Livewire::test(OtApproval::class)
            ->set('selected', [$ticked->id])
            ->set('partReason', 'Not authorised')
            ->call('refuseSelected')
            ->assertSee('Refused 1, skipped 0.');

        $this->assertSame(0, $ticked->fresh()->ot_approved_minutes);
        $this->assertNull($unticked->fresh()->ot_approved_minutes);
    }

    public function test_refuse_selected_requires_a_reason(): void
    {
        $company = $this->company();
        $employment = $this->profiled('2026-01-01', $company, ['ot_mode' => OtMode::EVENING]);
        $this->work($employment, ['2026-12-01' => ['08:00', '19:00']]);
        $card = Timecard::query()->where('employment_id', $employment->id)->firstOrFail();
        $this->loginAs('hr-manager', $company);

        Livewire::test(OtApproval::class)
            ->set('selected', [$card->id])
            ->set('partReason', '   ')
            ->call('refuseSelected')
            ->assertSee('A reason is required to refuse OT.')
            ->assertSet('selected', [$card->id]);

        $this->assertNull($card->fresh()->ot_approved_minutes);
    }

    public function test_reset_selected_clears_the_ticked_row_only(): void
    {
        $company = $this->company();
        $employment = $this->profiled('2026-01-01', $company, ['ot_mode' => OtMode::EVENING]);
        $this->work($employment, [
            '2026-12-01' => ['08:00', '19:00'],
            '2026-12-02' => ['08:00', '19:00'],
        ]);
        $ticked = Timecard::query()->where('employment_id', $employment->id)->whereDate('work_date', '2026-12-01')->firstOrFail();
        $unticked = Timecard::query()->where('employment_id', $employment->id)->whereDate('work_date', '2026-12-02')->firstOrFail();
        $user = $this->loginAs('hr-manager', $company);
        $service = app(OtApprovalService::class);
        $service->approve($ticked, null, $user);
        $service->approve($unticked, null, $user);

        Livewire::test(OtApproval::class)
            ->set('selected', [$ticked->id])
            ->call('resetSelected')
            ->assertSee('Reset 1, skipped 0.');

        $this->assertNull($ticked->fresh()->ot_approved_minutes);
        $this->assertNotNull($unticked->fresh()->ot_approved_minutes);
    }

    public function test_approve_part_ticked_opens_the_dialog_only_when_rows_are_ticked(): void
    {
        $company = $this->company();
        $employment = $this->profiled('2026-01-01', $company, ['ot_mode' => OtMode::EVENING]);
        $this->work($employment, [
            '2026-12-01' => ['08:00', '19:00'],
            '2026-12-02' => ['08:00', '19:00'],
        ]);
        $ticked = Timecard::query()->where('employment_id', $employment->id)->whereDate('work_date', '2026-12-01')->firstOrFail();
        $unticked = Timecard::query()->where('employment_id', $employment->id)->whereDate('work_date', '2026-12-02')->firstOrFail();
        $this->loginAs('hr-manager', $company);

        Livewire::test(OtApproval::class)
            ->call('openPartSelected')
            ->assertSet('partBulkOpen', false)
            ->assertSee('Tick at least one row.')
            ->assertDontSee('Minutes in steps of 30.')
            ->set('selected', [$ticked->id])
            ->call('openPartSelected')
            ->assertSet('partBulkOpen', true)
            ->assertSee('Minutes in steps of 30. A reason is required when approving less than the computed OT.')
            ->set('partMinutes', '60')
            ->set('partReason', 'Short day')
            ->call('approvePartSelected')
            ->assertSet('partBulkOpen', false)
            ->assertSee('Approved 1, skipped 0.');

        $this->assertSame(60, $ticked->fresh()->ot_approved_minutes);
        $this->assertNull($unticked->fresh()->ot_approved_minutes);
    }

    public function test_approve_part_selected_writes_the_minutes_on_the_ticked_row_only(): void
    {
        $company = $this->company();
        $employment = $this->profiled('2026-01-01', $company, ['ot_mode' => OtMode::EVENING]);
        $this->work($employment, [
            '2026-12-01' => ['08:00', '19:00'],
            '2026-12-02' => ['08:00', '19:00'],
        ]);
        $ticked = Timecard::query()->where('employment_id', $employment->id)->whereDate('work_date', '2026-12-01')->firstOrFail();
        $unticked = Timecard::query()->where('employment_id', $employment->id)->whereDate('work_date', '2026-12-02')->firstOrFail();
        $this->loginAs('hr-manager', $company);

        Livewire::test(OtApproval::class)
            ->set('selected', [$ticked->id])
            ->set('partMinutes', '60')
            ->set('partReason', 'Short day')
            ->call('approvePartSelected')
            ->assertSee('Approved 1, skipped 0.');

        $this->assertSame(60, $ticked->fresh()->ot_approved_minutes);
        $this->assertNull($unticked->fresh()->ot_approved_minutes);
    }

    public function test_bulk_approve_reports_skipped_rows(): void
    {
        $company = $this->company();
        $other = $this->company();
        $home = $this->profiled('2026-01-01', $company, ['ot_mode' => OtMode::EVENING]);
        $away = $this->profiled('2026-01-01', $other, ['ot_mode' => OtMode::EVENING]);
        $this->work($home, ['2026-12-01' => ['08:00', '19:00']]);
        $this->work($away, ['2026-12-01' => ['08:00', '19:00']]);
        $ids = Timecard::withoutGlobalScopes()->whereIn('employment_id', [$home->id, $away->id])->pluck('id')->all();
        $this->loginAs('hr-manager', $company);

        Livewire::test(OtApproval::class)
            ->set('selected', $ids)
            ->call('approveSelected')
            ->assertSee('Approved 1, skipped 1.');
    }

    public function test_partial_approval_requires_a_reason(): void
    {
        $company = $this->company();
        $employment = $this->profiled('2026-01-01', $company, ['ot_mode' => OtMode::EVENING]);
        $this->work($employment, ['2026-12-01' => ['08:00', '19:00']]);
        $card = Timecard::query()->where('employment_id', $employment->id)->firstOrFail();
        $this->loginAs('hr-manager', $company);

        Livewire::test(OtApproval::class)
            ->call('openPart', $card->id)
            ->assertSee('fixed inset-0', false)
            ->set('partMinutes', '60')
            ->set('partReason', '')
            ->call('approvePart')
            ->assertSee('A reason is required when approving less than computed OT.');

        $this->assertNull($card->fresh()->ot_approved_minutes);
    }

    public function test_screen_needs_ot_approve(): void
    {
        $company = $this->company();
        $this->loginAs('hr-officer', $company);

        $this->get(route('attendance.ot'))->assertForbidden();
    }
}
