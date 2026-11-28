<?php

namespace Modules\CoreHr\Tests\Feature\Screens;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Maatwebsite\Excel\Facades\Excel;
use Modules\CoreHr\Enums\ExitReason;
use Modules\CoreHr\Exports\EmployeesExport;
use Modules\CoreHr\Livewire\EmployeesIndex;
use Modules\CoreHr\Livewire\ResignedEmployeesIndex;
use Modules\CoreHr\Services\EmploymentService;
use Modules\Organisation\Enums\OrgUnitType;
use Modules\Organisation\Models\EmployeeCategory;
use Modules\Organisation\Models\OrgUnit;
use Tests\Support\HrFixtures;
use Tests\TestCase;

class EmployeesIndexTest extends TestCase
{
    use HrFixtures;
    use RefreshDatabase;

    public function test_list_needs_employee_view(): void
    {
        $this->actingAs(User::factory()->allCompanies()->create(['must_change_password' => false]))
            ->get(route('employees.index'))
            ->assertForbidden();
    }

    public function test_list_shows_only_active_employees_of_permitted_companies_by_default(): void
    {
        $a = $this->company();
        $b = $this->company();
        $active = $this->hired(c: $a);
        $other = $this->hired(c: $b);
        $exited = app(EmploymentService::class)->exit($this->hired('2026-01-01', $a), '2026-06-30', ExitReason::RESIGNATION);

        $this->loginAs('hr-officer', $a);

        Livewire::test(EmployeesIndex::class)
            ->assertSee($active->employee_no)
            ->assertDontSee($other->employee_no)
            ->assertDontSee($exited->employee_no);
    }

    public function test_list_renders_through_the_route(): void
    {
        $a = $this->company();
        $active = $this->hired(c: $a);

        $this->loginAs('hr-officer', $a);

        $this->get(route('employees.index'))
            ->assertOk()
            ->assertSee($active->employee_no)
            ->assertSee('Add employee');
    }

    public function test_list_shows_epf_service_period_and_actions_columns(): void
    {
        $a = $this->company();
        $active = $this->hired('2025-01-01', $a, $this->emp(['epf_no' => 'EPF-4421']));
        $active->person->update(['photo_path' => 'photos/'.$active->person_id.'/face.jpg']);

        $this->loginAs('hr-officer', $a);

        Livewire::test(EmployeesIndex::class)
            ->assertSee('EPF no')
            ->assertSee('Service period')
            ->assertSee('Profile')
            ->assertSeeHtml('>Actions</th>')
            ->assertSee('Department / subsection')
            ->assertSeeHtml('searchableSelect')
            ->assertSeeHtml('Search department')
            ->assertSeeHtml('Search designation')
            ->assertSeeHtml('for="employeeCategoryId"')
            ->assertSeeHtml('>Category</label>')
            ->assertSeeHtml('>Category</th>')
            ->assertDontSeeHtml('for="gradeId"')
            ->assertSee('EPF-4421')
            ->assertSee($active->employee_no)
            ->assertSeeHtml('title="View profile"')
            ->assertSeeHtml(route('persons.photo', $active->person_id, absolute: false).'?v=')
            ->assertSeeHtml('aria-label="View"')
            ->assertSeeHtml('aria-label="Edit"')
            ->assertSeeHtml('wire:click="openEdit('.$active->id.')"')
            ->assertDontSeeHtml('tab=personal')
            ->assertDontSeeHtml('>Grade</th>')
            ->assertDontSeeHtml('>Status</th>')
            ->assertDontSeeHtml('>Ready</th>');
    }

    public function test_category_filter_limits_the_list(): void
    {
        $a = $this->company();
        $catA = EmployeeCategory::factory()->create(['name' => 'Category A', 'is_active' => true]);
        $catB = EmployeeCategory::factory()->create(['name' => 'Category B', 'is_active' => true]);

        $inA = $this->hired(c: $a, emp: ['employee_category_id' => $catA->id]);
        $inB = $this->hired(c: $a, emp: ['employee_category_id' => $catB->id]);

        $this->loginAs('hr-officer', $a);

        Livewire::test(EmployeesIndex::class)
            ->assertSeeHtml('>Category A</td>')
            ->assertSeeHtml('>Category B</td>')
            ->set('employeeCategoryId', $catA->id)
            ->assertSee($inA->employee_no)
            ->assertSeeHtml('>Category A</td>')
            ->assertDontSee($inB->employee_no)
            ->set('employeeCategoryId', $catB->id)
            ->assertSee($inB->employee_no)
            ->assertSeeHtml('>Category B</td>')
            ->assertDontSee($inA->employee_no);
    }

    public function test_view_only_user_sees_view_action_but_not_edit(): void
    {
        $a = $this->company();
        $this->hired(c: $a);

        $user = User::factory()->forCompanies([$a])->withPermissions('employee.view')->create(['must_change_password' => false]);
        $this->actingAs($user);

        Livewire::test(EmployeesIndex::class)
            ->assertSeeHtml('title="View profile"')
            ->assertSeeHtml('aria-label="View"')
            ->assertDontSeeHtml('aria-label="Edit"')
            ->assertDontSeeHtml('openEdit');
    }

    public function test_edit_opens_hire_style_modal_with_personal_step(): void
    {
        $a = $this->company();
        $active = $this->hired(c: $a);

        $this->loginAs('hr-officer', $a);

        Livewire::test(EmployeesIndex::class)
            ->call('openEdit', $active->id)
            ->assertSet('editingEmploymentId', $active->id)
            ->assertSet('editStep', 1)
            ->assertSee('Edit employee')
            ->assertSee('Personal details')
            ->assertSee('Employment')
            ->assertSee('Pay and bank')
            ->assertSee('Full name')
            ->assertSee('NIC');
    }

    public function test_edit_pay_step_shows_compensation_panel_when_permitted(): void
    {
        $a = $this->company();
        $active = $this->paid('2026-01-01', $a);

        $this->loginAs('hr-manager', $a);

        Livewire::test(EmployeesIndex::class)
            ->call('openEdit', $active->id)
            ->call('setEditStep', 3)
            ->assertSee('Payroll details')
            ->assertSee('60000.00')
            ->assertSee('Change compensation')
            ->assertSee('Add component');
    }

    public function test_edit_pay_step_hides_compensation_without_permission_but_bank_still_works(): void
    {
        $a = $this->company();
        $active = $this->hired(c: $a);

        $user = User::factory()->forCompanies([$a])->withPermissions(
            'employee.view',
            'employee.update',
            'employee.bank.view',
            'employee.bank.manage',
        )->create(['must_change_password' => false]);
        $this->actingAs($user);

        Livewire::test(EmployeesIndex::class)
            ->call('openEdit', $active->id)
            ->call('setEditStep', 3)
            ->assertSee('No permission to view compensation.')
            ->assertDontSee('New compensation')
            ->assertSee('Current account')
            ->assertSee('Change bank account');
    }

    public function test_status_filter_shows_exited(): void
    {
        $a = $this->company();
        $active = $this->hired(c: $a);
        $exited = app(EmploymentService::class)->exit($this->hired('2026-01-01', $a), '2026-06-30', ExitReason::RESIGNATION);

        $this->loginAs('hr-officer', $a);

        Livewire::test(ResignedEmployeesIndex::class)
            ->set('status', 'EXITED')
            ->assertSee($exited->employee_no)
            ->assertDontSee($active->employee_no);
    }

    public function test_search_by_employee_number_name_and_nic_in_either_format(): void
    {
        $a = $this->company();
        $person = $this->person([
            'nic' => '853400937V',
            'date_of_birth' => '1985-12-05',
            'first_name' => 'Nimal',
            'last_name' => 'Perera',
            'name_with_initials' => 'N. Perera',
        ]);
        $target = $this->hired('2026-01-01', $a, p: $person);
        $other = $this->hired('2026-01-01', $a);

        $this->loginAs('hr-officer', $a);

        Livewire::test(EmployeesIndex::class)
            ->set('search', '198534000937')->assertSee($target->employee_no)->assertDontSee($other->employee_no)
            ->set('search', '853400937v')->assertSee($target->employee_no)->assertDontSee($other->employee_no)
            ->set('search', $target->employee_no)->assertSee($target->employee_no)->assertDontSee($other->employee_no)
            ->set('search', 'N. Perera')->assertSee($target->employee_no)->assertDontSee($other->employee_no);
    }

    public function test_org_unit_filter_includes_units_beneath_it(): void
    {
        $a = $this->company();
        $prod = OrgUnit::factory()->create(['company_id' => $a->id, 'type' => OrgUnitType::DEPARTMENT]);
        $paint = OrgUnit::factory()->create(['company_id' => $a->id, 'type' => OrgUnitType::SECTION, 'parent_id' => $prod->id]);
        $other = OrgUnit::factory()->create(['company_id' => $a->id, 'type' => OrgUnitType::DEPARTMENT]);

        $inPaint = app(EmploymentService::class)->hire(
            $this->person(),
            $a,
            $this->emp(),
            $this->asg($a, ['org_unit_id' => $paint->id]),
        );

        $this->loginAs('hr-officer', $a);

        Livewire::test(EmployeesIndex::class)
            ->set('orgUnitId', $prod->id)
            ->assertSee($inPaint->employee_no)
            ->set('orgUnitId', $other->id)
            ->assertDontSee($inPaint->employee_no);
    }

    public function test_empty_state_is_shown_when_nothing_matches(): void
    {
        $a = $this->company();
        $this->hired(c: $a);
        $this->loginAs('hr-officer', $a);

        Livewire::test(EmployeesIndex::class)
            ->set('search', 'zzzzzz-nobody')
            ->assertSee('No employees match these filters.');
    }

    public function test_export_respects_filters_and_never_contains_bank_details(): void
    {
        $a = $this->company();
        $this->hired(c: $a);
        $this->loginAs('hr-officer', $a);

        Excel::fake();

        Livewire::test(EmployeesIndex::class)
            ->set('status', 'ACTIVE')
            ->call('export');

        Excel::assertDownloaded('employees.xlsx', function (EmployeesExport $export) {
            $headings = $export->headings();

            return in_array('EPF no.', $headings, true)
                && in_array('NIC', $headings, true)
                && collect($headings)->every(fn (string $heading) => ! str_contains($heading, 'Account') && ! str_contains($heading, 'Bank'));
        });
    }

    public function test_export_rows_contain_the_filtered_employees_only(): void
    {
        $a = $this->company();
        $active = $this->hired(c: $a);
        $exited = app(EmploymentService::class)->exit($this->hired('2026-01-01', $a), '2026-06-30', ExitReason::RESIGNATION);
        $this->loginAs('hr-officer', $a);

        Excel::fake();

        Livewire::test(EmployeesIndex::class)->call('export');

        Excel::assertDownloaded('employees.xlsx', function (EmployeesExport $export) use ($active, $exited) {
            $numbers = $export->query()->get()->pluck('employee_no')->all();

            return in_array($active->employee_no, $numbers, true) && ! in_array($exited->employee_no, $numbers, true);
        });
    }
}
