<?php

namespace Modules\Payroll\Tests\Feature\Screens;

use App\Support\Company\CompanyContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\CoreHr\Livewire\EmployeesIndex;
use Modules\Payroll\Livewire\EmployeeCompensationPanel;
use Modules\Payroll\Models\EmployeeComponent;
use Modules\Payroll\Services\CompensationService;
use Tests\Support\HrFixtures;
use Tests\TestCase;

class EmployeeCompensationPanelTest extends TestCase
{
    use HrFixtures;
    use RefreshDatabase;

    public function test_add_component_opens_without_error_and_excludes_open_codes(): void
    {
        $company = $this->company();
        $employment = $this->paid('2026-01-01', $company, [], [
            'PERF' => '5000.00',
        ]);

        $this->loginAs('hr-manager', $company);
        CompanyContext::set($company->id);

        Livewire::test(EmployeeCompensationPanel::class, ['employment' => $employment])
            ->call('openComponent')
            ->assertHasNoErrors()
            ->assertSet('showComponentForm', true)
            ->assertSet('component_kind', 'EARNING')
            ->assertSee('Select')
            ->assertSee('Earning')
            ->assertSee('Deduction')
            ->assertDontSee('PERF — Performance allowance')
            ->assertSee('TRAVEL — Travel allowance')
            ->assertSeeHtml('INCENTIVE_1')
            ->set('component_kind', 'DEDUCTION')
            ->assertDontSee('TRAVEL — Travel allowance')
            ->assertSee('HOSTEL — Hostel');
    }

    public function test_table_shows_open_components_only(): void
    {
        $this->travelTo('2026-06-15');

        $company = $this->company();
        $employment = $this->paid('2026-01-01', $company, [], [
            'PERF' => '5000.00',
            'TRAVEL' => '2500.00',
            'HOSTEL' => '1500.00',
        ]);

        $perf = EmployeeComponent::query()
            ->where('employment_id', $employment->id)
            ->whereHas('payComponent', fn ($q) => $q->where('code', 'PERF'))
            ->firstOrFail();

        app(CompensationService::class)->endComponent($perf, '2026-05-31');

        $this->loginAs('hr-manager', $company);
        CompanyContext::set($company->id);

        Livewire::test(EmployeeCompensationPanel::class, ['employment' => $employment->fresh()])
            ->assertSee('Earnings')
            ->assertSee('Deductions')
            ->assertSee('TRAVEL')
            ->assertSee('2500.00')
            ->assertSee('HOSTEL')
            ->assertSee('1500.00')
            ->assertDontSee('5000.00')
            ->call('openComponent')
            ->assertSeeHtml('PERF')
            ->assertDontSeeHtml('"id":"TRAVEL"')
            ->set('component_kind', 'DEDUCTION')
            ->assertDontSeeHtml('"id":"HOSTEL"');
    }

    public function test_edit_modal_pay_step_can_open_add_component(): void
    {
        $company = $this->company();
        $employment = $this->paid('2026-01-01', $company);

        $this->loginAs('hr-manager', $company);

        Livewire::test(EmployeesIndex::class)
            ->call('openEdit', $employment->id)
            ->call('setEditStep', 3)
            ->assertSee('Add component')
            ->assertSee('Earnings')
            ->assertSee('Deductions');

        Livewire::test(EmployeeCompensationPanel::class, ['employment' => $employment])
            ->call('openComponent')
            ->assertHasNoErrors()
            ->assertSet('showComponentForm', true)
            ->assertSee('Search component', false);
    }
}
