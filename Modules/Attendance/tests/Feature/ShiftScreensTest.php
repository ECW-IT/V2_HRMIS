<?php

namespace Modules\Attendance\Tests\Feature;

use App\Models\User;
use App\Support\Company\CompanyContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Attendance\Livewire\ShiftsIndex;
use Modules\Attendance\Livewire\TimeProfilesBulk;
use Modules\Attendance\Services\TimeProfileService;
use Modules\CoreHr\Livewire\HireWizard;
use Modules\CoreHr\Models\Employment;
use Spatie\Permission\Models\Permission;
use Tests\Support\HrFixtures;
use Tests\TestCase;

class ShiftScreensTest extends TestCase
{
    use HrFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Permission::findOrCreate('org.view');
        Permission::findOrCreate('shift.manage');
        Permission::findOrCreate('employee.create');
        Permission::findOrCreate('employee.update');
        Permission::findOrCreate('employee.bank.manage');
    }

    public function test_shifts_screen_needs_org_view_and_saving_needs_shift_manage(): void
    {
        $without = User::factory()->create(['must_change_password' => false]);
        $this->actingAs($without)->get(route('attendance.shifts.index'))->assertForbidden();

        $viewer = User::factory()->allCompanies()->withPermissions('org.view')->create([
            'must_change_password' => false,
        ]);
        $this->actingAs($viewer)->get(route('attendance.shifts.index'))->assertOk();

        Livewire::actingAs($viewer)
            ->test(ShiftsIndex::class)
            ->call('openCreate')
            ->assertForbidden();

        $manager = User::factory()->allCompanies()->withPermissions('org.view', 'shift.manage')->create([
            'must_change_password' => false,
        ]);

        Livewire::actingAs($manager)
            ->test(ShiftsIndex::class)
            ->call('openCreate')
            ->set('code', 'T0800')
            ->set('name', 'Test')
            ->set('start_time', '08:00')
            ->set('end_time', '17:00')
            ->set('late_from', '08:06')
            ->set('morning_cutoff', '10:00')
            ->set('half_day_from', '12:30')
            ->set('early_leave_from', '15:00')
            ->call('save')
            ->assertHasNoErrors();
    }

    public function test_preview_text_reflects_the_entered_times(): void
    {
        $user = User::factory()->allCompanies()->withPermissions('org.view', 'shift.manage')->create([
            'must_change_password' => false,
        ]);

        Livewire::actingAs($user)
            ->test(ShiftsIndex::class)
            ->call('openCreate')
            ->set('early_leave_from', '15:30')
            ->assertSee('15:30');
    }

    public function test_hire_wizard_saves_a_time_profile_from_the_hire_date(): void
    {
        $c = $this->company(['employee_no_prefix' => 'ECW', 'next_employee_seq' => 1]);
        $a = $this->asg($c);
        $shift = $this->shift();
        $this->loginAs('hr-manager', $c);

        Livewire::test(HireWizard::class)
            ->set('companyId', $c->id)->set('nic', '853400937V')->call('next')
            ->set('firstName', 'Nimal')->set('lastName', 'Perera')->set('nameWithInitials', 'N. Perera')->set('mobile', '0771234567')
            ->set('addressLine1', '12 Main St')->set('city', 'Nittambuwa')->call('next')
            ->set('type', 'PERMANENT')->set('hireDate', '2026-12-01')->set('epfNo', '900')
            ->set('orgUnitId', $a['org_unit_id'])->set('jobId', $a['job_id'])->set('gradeId', $a['grade_id'])->set('locationId', $a['location_id'])
            ->set('shiftId', $shift->id)->call('next')
            ->set('isCash', true)->call('next')
            ->call('hire')
            ->assertHasNoErrors();

        $e = Employment::sole();
        $profile = app(TimeProfileService::class)->on($e, '2026-12-01');
        $this->assertNotNull($profile);
        $this->assertSame($shift->id, $profile->shift_id);
        $this->assertSame('2026-12-01', $profile->effective_from->toDateString());
    }

    public function test_bulk_screen_changes_the_shift_for_the_ticked_employees_only(): void
    {
        $c = $this->company();
        $s1 = $this->shift(['code' => 'S1']);
        $s2 = $this->shift(['code' => 'S2']);
        $a = $this->profiled('2026-01-01', $c, ['shift_id' => $s1->id]);
        $b = $this->profiled('2026-01-01', $c, ['shift_id' => $s1->id]);

        $user = User::factory()->forCompanies([$c])->withPermissions('employee.update')->create([
            'must_change_password' => false,
        ]);
        $this->actingAs($user);
        CompanyContext::set($c->id);

        Livewire::actingAs($user)
            ->test(TimeProfilesBulk::class)
            ->call('toggle', $a->id)
            ->set('effective_from', '2026-07-01')
            ->set('shift_id', $s2->id)
            ->call('apply')
            ->assertHasNoErrors();

        $this->assertSame($s2->id, app(TimeProfileService::class)->on($a, '2026-07-01')->shift_id);
        $this->assertSame($s1->id, app(TimeProfileService::class)->on($b, '2026-07-01')->shift_id);
    }
}
