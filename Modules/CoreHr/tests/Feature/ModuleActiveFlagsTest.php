<?php

namespace Modules\CoreHr\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Modules\Attendance\Models\BiometricEnrolment;
use Modules\Attendance\Services\EnrolmentService;
use Modules\CoreHr\Database\Seeders\RegionSeeder;
use Modules\CoreHr\Database\Seeders\RelationshipSeeder;
use Modules\CoreHr\Livewire\EmployeeProfile;
use Modules\CoreHr\Livewire\EmployeesIndex;
use Modules\CoreHr\Livewire\HireWizard;
use Modules\CoreHr\Livewire\ResignedEmployeesIndex;
use Modules\CoreHr\Livewire\Tabs\EditEmploymentStep;
use Modules\CoreHr\Livewire\Tabs\Personal;
use Modules\CoreHr\Livewire\Tabs\Summary;
use Modules\CoreHr\Models\Employment;
use Modules\CoreHr\Services\EmploymentService;
use Modules\Organisation\Models\EmployeeCategory;
use Modules\Payroll\Enums\OtRateMode;
use Tests\Support\HrFixtures;
use Tests\TestCase;

class ModuleActiveFlagsTest extends TestCase
{
    use HrFixtures;
    use RefreshDatabase;

    public function test_hire_with_attendance_active_requires_fingerprint(): void
    {
        $this->seed(RegionSeeder::class);
        $this->seed(RelationshipSeeder::class);
        $c = $this->company();
        $a = $this->asg($c);
        $categoryId = EmployeeCategory::factory()->create()->id;
        $this->loginAs('hr-manager', $c);
        $this->travelTo('2026-12-01');

        $component = Livewire::test(HireWizard::class)
            ->set('companyId', $c->id)->set('nic', '853400937V')->call('next');

        foreach ([
            'fullName' => 'Nimal Perera',
            'callingName' => 'Nimal',
            'maritalStatus' => 'MARRIED',
            'nationality' => 'Sri Lankan',
            'region' => 'Buddhism',
            'arriveWorkFrom' => 'HOME',
            'province' => 'Western',
            'electorateDistrict' => 'Gampaha',
            'gnDivision' => 'Nittambuwa North',
            'mobile' => '0771234567',
            'addressLine1' => '12 Main St',
            'city' => 'Nittambuwa',
            'emergencyContacts' => [
                ['name' => 'Kamala Perera', 'relationship' => 'Wife', 'phone' => '0771111111'],
            ],
        ] as $property => $value) {
            $component->set($property, $value);
        }

        $component->call('next')
            ->set('type', 'PERMANENT')->set('hireDate', '2026-12-01')->set('attendanceActive', true)
            ->set('employeeCategoryId', $categoryId)
            ->set('departmentId', $a['org_unit_id'])->set('orgUnitId', $a['org_unit_id'])->set('designationId', $a['designation_id'])
            ->set('shiftId', $this->shift()->id)->call('next')
            ->set('basicSalary', '55000.00')->set('otRateMode', OtRateMode::CALCULATED->value)
            ->set('isCash', true)->call('next')
            ->assertHasErrors(['deviceGroup', 'deviceUserId']);
    }

    public function test_hire_with_attendance_inactive_skips_enrolment(): void
    {
        $c = $this->company();
        $this->loginAs('hr-manager', $c);

        Livewire::test(HireWizard::class); // warm
        $component = $this->walkHire($c, ['attendanceActive' => false]);
        $component->call('hire')->assertHasNoErrors()->assertRedirect();

        $e = Employment::sole();
        $this->assertFalse($e->attendance_active);
        $this->assertTrue($e->hrmis_active);
        $this->assertTrue($e->payroll_active);
        $this->assertSame(0, BiometricEnrolment::query()->count());
    }

    public function test_hrmis_inactive_is_hidden_from_employees_and_shown_on_resigned_list(): void
    {
        $c = $this->company();
        $active = $this->hired(c: $c);
        $resigned = $this->hired(c: $c, emp: ['hrmis_active' => false]);
        $this->loginAs('hr-officer', $c);

        Livewire::test(EmployeesIndex::class)
            ->assertSee($active->employee_no)
            ->assertDontSee($resigned->employee_no);

        Livewire::test(ResignedEmployeesIndex::class)
            ->assertSee($resigned->employee_no)
            ->assertDontSee($active->employee_no);

        $this->get(route('employees.resigned'))
            ->assertOk()
            ->assertSee($resigned->employee_no)
            ->assertSee('Resigned');
    }

    public function test_inactive_attendance_does_not_resolve_punches(): void
    {
        $c = $this->company();
        $e = $this->hired(c: $c, emp: ['attendance_active' => true]);
        app(EnrolmentService::class)->enrol($e, 'NIT', 42, $e->hire_date);
        app(EmploymentService::class)->updateModuleFlags($e, ['attendance_active' => false], $e->hire_date);

        $this->assertNull(app(EnrolmentService::class)->resolve('NIT', 42, $e->hire_date->toDateString().' 08:00:00'));
        $this->assertNotNull(BiometricEnrolment::query()->where('employment_id', $e->id)->whereNotNull('valid_to')->first());
    }

    public function test_profile_can_update_attendance_and_payroll_flags(): void
    {
        $c = $this->company();
        $e = $this->hired(c: $c);
        $this->loginAs('hr-manager', $c);

        Livewire::test(Summary::class, ['employment' => $e])
            ->set('attendanceActive', false)
            ->set('payrollActive', false)
            ->call('saveModuleFlags')
            ->assertHasNoErrors()
            ->assertSee('Module flags updated.');

        $e->refresh();
        $this->assertTrue($e->hrmis_active);
        $this->assertFalse($e->attendance_active);
        $this->assertFalse($e->payroll_active);
    }

    public function test_turning_hrmis_off_opens_exit_and_does_not_clear_the_flag_yet(): void
    {
        $c = $this->company();
        $e = $this->hired(c: $c);
        $this->loginAs('hr-manager', $c);

        Livewire::test(Summary::class, ['employment' => $e])
            ->set('hrmisActive', false)
            ->assertSet('hrmisActive', true)
            ->assertSee('Complete the exit form to deactivate HRMIS.')
            ->assertDispatched('open-exit-action');

        $e->refresh();
        $this->assertTrue($e->hrmis_active);

        Livewire::test(Summary::class, ['employment' => $e])
            ->set('attendanceActive', false)
            ->call('saveModuleFlags')
            ->assertHasNoErrors()
            ->assertSee('Module flags updated.');

        $e->refresh();
        $this->assertTrue($e->hrmis_active);
        $this->assertFalse($e->attendance_active);
    }

    public function test_turning_hrmis_off_from_edit_step_opens_exit_immediately(): void
    {
        $c = $this->company();
        $e = $this->hired(c: $c);
        $this->loginAs('hr-manager', $c);

        Livewire::test(EditEmploymentStep::class, ['employment' => $e])
            ->set('hrmisActive', false)
            ->assertSet('hrmisActive', true)
            ->assertSee('Complete the exit form to deactivate HRMIS.')
            ->assertDispatched('open-exit-action');

        $e->refresh();
        $this->assertTrue($e->hrmis_active);
    }

    public function test_turning_hrmis_off_from_profile_opens_the_exit_dialog(): void
    {
        $c = $this->company();
        $e = $this->hired(c: $c);
        $this->loginAs('hr-manager', $c);

        Livewire::test(EmployeeProfile::class, ['employment' => $e])
            ->assertSet('action', null)
            ->call('openExitFromModuleFlags')
            ->assertSet('action', 'exit')
            ->assertSee('Complete the exit form to deactivate HRMIS.')
            ->assertSee('Exit employee');
    }

    public function test_resigned_employee_can_update_only_payroll_active(): void
    {
        $c = $this->company();
        $e = $this->hired(c: $c, emp: [
            'hrmis_active' => false,
            'attendance_active' => false,
            'payroll_active' => true,
        ]);
        $this->loginAs('hr-manager', $c);

        Livewire::test(Summary::class, ['employment' => $e])
            ->set('hrmisActive', true)
            ->set('attendanceActive', true)
            ->set('payrollActive', false)
            ->call('saveModuleFlags')
            ->assertHasNoErrors()
            ->assertSee('Module flags updated.');

        $e->refresh();
        $this->assertFalse($e->hrmis_active);
        $this->assertFalse($e->attendance_active);
        $this->assertFalse($e->payroll_active);
    }

    public function test_resigned_employee_personal_save_is_forbidden(): void
    {
        $c = $this->company();
        $e = $this->hired(c: $c, emp: ['hrmis_active' => false]);
        $this->loginAs('hr-manager', $c);

        Livewire::test(Personal::class, ['employment' => $e])
            ->set('callingName', 'Sunil')
            ->call('save')
            ->assertForbidden();
    }

    public function test_resigned_list_filters_by_payroll_active(): void
    {
        $c = $this->company();
        $payrollOn = $this->hired(c: $c, emp: ['hrmis_active' => false, 'payroll_active' => true]);
        $payrollOff = $this->hired(c: $c, emp: ['hrmis_active' => false, 'payroll_active' => false]);
        $this->loginAs('hr-officer', $c);

        Livewire::test(ResignedEmployeesIndex::class)
            ->assertSee($payrollOn->employee_no)
            ->assertSee($payrollOff->employee_no)
            ->set('payrollActive', '1')
            ->assertSee($payrollOn->employee_no)
            ->assertDontSee($payrollOff->employee_no)
            ->set('payrollActive', '0')
            ->assertSee($payrollOff->employee_no)
            ->assertDontSee($payrollOn->employee_no);
    }

    public function test_open_edit_is_forbidden_for_resigned_employees(): void
    {
        $c = $this->company();
        $e = $this->hired(c: $c, emp: ['hrmis_active' => false]);
        $this->loginAs('hr-manager', $c);

        Livewire::test(EmployeesIndex::class)
            ->call('openEdit', $e->id)
            ->assertForbidden();
    }

    /** @param  array<string, mixed>  $employment */
    private function walkHire($company, array $employment = []): Testable
    {
        $this->seed(RegionSeeder::class);
        $this->seed(RelationshipSeeder::class);
        $this->travelTo($employment['hireDate'] ?? '2026-12-01');
        $a = $this->asg($company);
        $categoryId = EmployeeCategory::factory()->create()->id;

        $component = Livewire::test(HireWizard::class)
            ->set('companyId', $company->id)->set('nic', '853400937V')->call('next');

        foreach ([
            'fullName' => 'Nimal Perera',
            'callingName' => 'Nimal',
            'maritalStatus' => 'MARRIED',
            'nationality' => 'Sri Lankan',
            'region' => 'Buddhism',
            'arriveWorkFrom' => 'HOME',
            'province' => 'Western',
            'electorateDistrict' => 'Gampaha',
            'gnDivision' => 'Nittambuwa North',
            'mobile' => '0771234567',
            'addressLine1' => '12 Main St',
            'city' => 'Nittambuwa',
            'emergencyContacts' => [
                ['name' => 'Kamala Perera', 'relationship' => 'Wife', 'phone' => '0771111111'],
            ],
        ] as $property => $value) {
            $component->set($property, $value);
        }
        $component->call('next');

        foreach (array_merge([
            'type' => 'PERMANENT',
            'hireDate' => '2026-12-01',
            'attendanceActive' => false,
            'employeeCategoryId' => $categoryId,
            'departmentId' => $a['org_unit_id'],
            'orgUnitId' => $a['org_unit_id'],
            'designationId' => $a['designation_id'],
            'shiftId' => $this->shift()->id,
        ], $employment) as $property => $value) {
            $component->set($property, $value);
        }
        $component->call('next')
            ->set('basicSalary', '55000.00')
            ->set('otRateMode', OtRateMode::CALCULATED->value)
            ->set('isCash', true)
            ->call('next');

        return $component;
    }
}
