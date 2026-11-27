<?php

namespace Modules\Attendance\Tests\Feature;

use App\Models\User;
use App\Support\Company\CompanyContext;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Attendance\Enums\PunchResolution;
use Modules\Attendance\Exceptions\InvalidTimeProfile;
use Modules\Attendance\Livewire\PeriodShiftsIndex;
use Modules\Attendance\Models\Punch;
use Modules\Attendance\Models\Timecard;
use Modules\Attendance\Services\ShiftService;
use Modules\Attendance\Services\TimecardService;
use Modules\Attendance\Services\TimeProfileService;
use Modules\Calendar\Services\PayPeriodService;
use Modules\CoreHr\Models\Employment;
use Modules\CoreHr\Services\EmploymentService;
use Modules\Organisation\Enums\OrgUnitType;
use Modules\Organisation\Models\OrgUnit;
use Tests\Support\HrFixtures;
use Tests\TestCase;

class PeriodShiftRulesTest extends TestCase
{
    use HrFixtures;
    use RefreshDatabase;

    private TimeProfileService $svc;

    protected function setUp(): void
    {
        parent::setUp();

        $this->svc = app(TimeProfileService::class);
    }

    public function test_custom_late_from_is_used_and_a_later_shift_edit_does_not_change_it(): void
    {
        $this->travelTo('2026-12-10 09:00:00');
        $shift = $this->shift();
        $e = $this->profiled('2026-01-01', profile: ['shift_id' => $shift->id, 'late_deduction' => true]);

        Punch::factory()->create([
            'employment_id' => $e->id,
            'resolution' => PunchResolution::RESOLVED,
            'punched_at' => '2026-12-01 08:20:00',
        ]);
        Punch::factory()->create([
            'employment_id' => $e->id,
            'resolution' => PunchResolution::RESOLVED,
            'punched_at' => '2026-12-01 17:00:00',
        ]);

        $this->svc->set($e, [
            'effective_from' => '2026-12-01',
            'shift_id' => $shift->id,
            'late_deduction' => true,
            'times' => $this->times(['late_from' => '08:15']),
            'breaks' => [],
        ]);

        $card = app(TimecardService::class)->compute($e, '2026-12-01');
        $this->assertSame(6, $card->late_in_minutes);

        app(ShiftService::class)->update($shift->fresh(), ['late_from' => '08:01:00']);

        $again = Timecard::withoutGlobalScopes()
            ->where('employment_id', $e->id)
            ->whereDate('work_date', '2026-12-01')
            ->first();

        $this->assertSame(6, $again->late_in_minutes);
        $this->assertSame('08:01:00', Carbon::parse($shift->fresh()->late_from)->format('H:i:s'));
    }

    public function test_december_assignment_stays_in_force_until_a_new_profile(): void
    {
        $shift = $this->shift();
        $e = $this->profiled('2026-01-01', profile: ['shift_id' => $shift->id]);

        $this->svc->set($e, [
            'effective_from' => '2026-12-01',
            'shift_id' => $shift->id,
            'ot_mode' => 'EVENING',
            'late_deduction' => true,
            'times' => $this->times(['late_from' => '08:15']),
            'breaks' => [['name' => 'Dinner', 'start_time' => '20:00', 'end_time' => '20:30', 'is_paid' => false]],
        ]);

        $january = $this->svc->on($e, '2027-01-15');
        $this->assertTrue($january->uses_custom_times);
        $this->assertSame('08:15:00', Carbon::parse($january->late_from)->format('H:i:s'));
        $this->assertNull($january->effective_to);
        $this->assertSame('Dinner', $january->breaks()->sole()->name);

        $copied = $this->svc->set($e, [
            'effective_from' => '2027-03-01',
            'shift_id' => $shift->id,
        ]);
        $this->assertTrue($copied->uses_custom_times);
        $this->assertSame('08:15:00', Carbon::parse($copied->late_from)->format('H:i:s'));
        $this->assertSame('Dinner', $copied->breaks()->sole()->name);

        $stillJanuary = $this->svc->on($e, '2027-01-15');
        $this->assertSame('2027-02-28', $stillJanuary->effective_to->toDateString());
        $this->assertSame('08:15:00', Carbon::parse($stillJanuary->late_from)->format('H:i:s'));

        $cleared = $this->svc->set($e, [
            'effective_from' => '2027-04-01',
            'shift_id' => $shift->id,
            'times' => null,
        ]);
        $this->assertFalse($cleared->uses_custom_times);
        $this->assertSame(0, $cleared->breaks()->count());
        $this->assertTrue($this->svc->on($e, '2027-03-15')->uses_custom_times);
    }

    public function test_two_selected_departments_are_updated_and_another_is_not(): void
    {
        $this->travelTo('2026-12-10 09:00:00');
        $company = $this->company();
        $shift = $this->shift();
        $deptA = OrgUnit::factory()->create(['company_id' => $company->id, 'name' => 'Cutting']);
        $section = OrgUnit::factory()->create([
            'company_id' => $company->id,
            'parent_id' => $deptA->id,
            'type' => OrgUnitType::SECTION,
            'name' => 'Line 1',
        ]);
        $deptB = OrgUnit::factory()->create(['company_id' => $company->id, 'name' => 'Sewing']);
        $deptC = OrgUnit::factory()->create(['company_id' => $company->id, 'name' => 'Stores']);

        $inSection = $this->moved($this->profiled('2026-01-01', $company, ['shift_id' => $shift->id]), $section->id);
        $inB = $this->moved($this->profiled('2026-01-01', $company, ['shift_id' => $shift->id]), $deptB->id);
        $inC = $this->moved($this->profiled('2026-01-01', $company, ['shift_id' => $shift->id]), $deptC->id);

        $user = User::factory()->forCompanies([$company])->withPermissions('employee.update')->create([
            'must_change_password' => false,
        ]);
        $this->actingAs($user);
        CompanyContext::set($company->id);

        Livewire::actingAs($user)
            ->test(PeriodShiftsIndex::class)
            ->set('selectedDepartments', [$deptA->id, $deptB->id])
            ->set('shift_id', $shift->id)
            ->set('start_time', '08:00')
            ->set('end_time', '17:00')
            ->set('late_from', '08:15')
            ->set('morning_cutoff', '10:00')
            ->set('half_day_from', '12:30')
            ->set('early_leave_from', '15:00')
            ->set('ot_mode', 'EVENING')
            ->set('late_deduction', true)
            ->set('breaks', [])
            ->call('apply')
            ->assertHasNoErrors()
            ->assertSet('formError', '');

        $this->assertTrue($this->svc->on($inSection, '2026-12-15')->uses_custom_times);
        $this->assertSame('08:15:00', Carbon::parse($this->svc->on($inB, '2026-12-15')->late_from)->format('H:i:s'));
        $this->assertSame('2026-12-01', $this->svc->on($inB, '2026-12-15')->effective_from->toDateString());
        $this->assertFalse($this->svc->on($inC, '2026-12-15')->uses_custom_times);
        $this->assertSame('2026-01-01', $this->svc->on($inC, '2026-12-15')->effective_from->toDateString());
    }

    public function test_ticked_employees_are_updated_and_an_unticked_employee_is_not(): void
    {
        $this->travelTo('2026-12-10 09:00:00');
        $company = $this->company();
        $shift = $this->shift();
        $ticked = $this->profiled('2026-01-01', $company, ['shift_id' => $shift->id]);
        $also = $this->profiled('2026-01-01', $company, ['shift_id' => $shift->id]);
        $unticked = $this->profiled('2026-01-01', $company, ['shift_id' => $shift->id]);

        $user = User::factory()->forCompanies([$company])->withPermissions('employee.update')->create([
            'must_change_password' => false,
        ]);
        $this->actingAs($user);
        CompanyContext::set($company->id);

        Livewire::actingAs($user)
            ->test(PeriodShiftsIndex::class)
            ->set('mode', 'employees')
            ->call('toggle', $ticked->id)
            ->call('toggle', $also->id)
            ->set('shift_id', $shift->id)
            ->set('start_time', '08:00')
            ->set('end_time', '17:00')
            ->set('late_from', '08:20')
            ->set('morning_cutoff', '10:00')
            ->set('half_day_from', '12:30')
            ->set('early_leave_from', '15:00')
            ->set('late_deduction', true)
            ->set('breaks', [])
            ->call('apply')
            ->assertHasNoErrors()
            ->assertSet('formError', '');

        $this->assertSame('08:20:00', Carbon::parse($this->svc->on($ticked, '2026-12-01')->late_from)->format('H:i:s'));
        $this->assertTrue($this->svc->on($also, '2027-01-01')->uses_custom_times);
        $this->assertFalse($this->svc->on($unticked, '2026-12-01')->uses_custom_times);
    }

    public function test_a_closed_period_is_refused(): void
    {
        $this->travelTo('2027-01-05');
        $e = $this->hired('2026-01-01');
        $periods = app(PayPeriodService::class);
        $periods->markClosed($periods->ensure($e->company, 2026, 12), null);

        $this->expectException(InvalidTimeProfile::class);
        $this->svc->set($e, [
            'effective_from' => '2026-12-15',
            'shift_id' => $this->shift()->id,
            'times' => $this->times(['late_from' => '08:20']),
            'breaks' => [],
        ]);
    }

    public function test_times_out_of_order_are_refused(): void
    {
        $this->expectException(InvalidTimeProfile::class);
        $this->svc->set($this->hired('2026-01-01'), [
            'effective_from' => '2026-01-01',
            'shift_id' => $this->shift()->id,
            'times' => $this->times(['late_from' => '07:00']),
            'breaks' => [],
        ]);
    }

    /** @param  array<string, string>  $overrides */
    private function times(array $overrides = []): array
    {
        return array_merge([
            'start_time' => '08:00',
            'end_time' => '17:00',
            'late_from' => '08:06',
            'morning_cutoff' => '10:00',
            'half_day_from' => '12:30',
            'early_leave_from' => '15:00',
        ], $overrides);
    }

    private function moved(Employment $employment, int $orgUnitId): Employment
    {
        app(EmploymentService::class)->changeAssignment($employment, '2026-12-01', [
            'org_unit_id' => $orgUnitId,
        ], 'Move');

        return $employment;
    }
}
