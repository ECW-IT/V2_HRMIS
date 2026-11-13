<?php

namespace Modules\CoreHr\Tests\Feature\Screens;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Maatwebsite\Excel\Facades\Excel;
use Modules\CoreHr\Enums\ExitReason;
use Modules\CoreHr\Exports\EmployeesExport;
use Modules\CoreHr\Livewire\EmployeesIndex;
use Modules\CoreHr\Services\EmploymentService;
use Modules\Organisation\Enums\OrgUnitType;
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
            ->assertSee('Hire employee');
    }

    public function test_status_filter_shows_exited(): void
    {
        $a = $this->company();
        $active = $this->hired(c: $a);
        $exited = app(EmploymentService::class)->exit($this->hired('2026-01-01', $a), '2026-06-30', ExitReason::RESIGNATION);

        $this->loginAs('hr-officer', $a);

        Livewire::test(EmployeesIndex::class)
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
