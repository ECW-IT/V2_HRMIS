<?php

namespace Modules\Payroll\Tests\Feature\Screens;

use App\Models\User;
use App\Support\Company\CompanyContext;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Payroll\Database\Seeders\PayComponentSeeder;
use Modules\Payroll\Database\Seeders\PayRuleSeeder;
use Modules\Payroll\Enums\OtRateMode;
use Modules\Payroll\Livewire\CompensationIndex;
use Modules\Payroll\Livewire\EmployeeCompensationPanel;
use Modules\Payroll\Models\EmployeeCompensation;
use Modules\Payroll\Services\CompensationService;
use Modules\Workflow\Models\ApprovalDefinition;
use Tests\Support\HrFixtures;
use Tests\TestCase;

class CompensationScreenTest extends TestCase
{
    use HrFixtures;
    use RefreshDatabase;

    public function test_screen_needs_its_permission_and_saves_a_row(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $company = $this->company();
        $guest = User::factory()->forCompanies([$company])->create(['must_change_password' => false]);
        $this->actingAs($guest);
        $this->get(route('payroll.compensation'))->assertForbidden();

        $this->loginAs('super-admin', $company);
        CompanyContext::set($company->id);
        $this->compensationDefinition();
        $employment = $this->hired('2026-01-01', $company);

        Livewire::test(CompensationIndex::class)
            ->set('employmentId', (string) $employment->id)
            ->call('openCompensation')
            ->set('from', '2026-01-01')
            ->set('basic_salary', '75000.00')
            ->set('ot_rate_mode', 'CALCULATED')
            ->set('change_reason', 'Opening pay')
            ->call('saveCompensation')
            ->assertHasNoErrors()
            ->assertSet('showCompensationForm', false)
            ->assertSee('75000.00');

        $this->assertTrue(EmployeeCompensation::query()->where('employment_id', $employment->id)->where('basic_salary', '75000.00')->exists());
    }

    public function test_change_compensation_prefills_current_values(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(PayComponentSeeder::class);
        $this->seed(PayRuleSeeder::class);
        $company = $this->company();
        $this->loginAs('super-admin', $company);
        CompanyContext::set($company->id);

        $employment = $this->hired('2026-01-01', $company);
        app(CompensationService::class)->set($employment, '2026-01-01', [
            'basic_salary' => '80000.00',
            'ot_rate_mode' => OtRateMode::FIXED,
            'ot_rate' => '350.00',
        ]);

        $this->travelTo('2026-11-15');

        Livewire::test(CompensationIndex::class)
            ->set('employmentId', (string) $employment->id)
            ->call('openCompensation')
            ->assertSet('showCompensationForm', true)
            ->assertSet('basic_salary', '80000.00')
            ->assertSet('ot_rate_mode', 'FIXED')
            ->assertSet('ot_rate', '350.00')
            ->assertSet('from', '2026-12-01');
    }

    public function test_panel_edits_an_open_component_amount(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $company = $this->company();
        $this->loginAs('super-admin', $company);
        CompanyContext::set($company->id);

        $this->compensationDefinition();
        $employment = $this->paid('2026-01-01', $company, [], ['FUEL' => '5000.00']);
        $fuel = app(CompensationService::class)->componentsOn($employment, '2026-01-01')->first(
            fn ($row) => $row->payComponent?->code === 'FUEL'
        );

        Livewire::test(EmployeeCompensationPanel::class, ['employment' => $employment])
            ->call('openEditComponent', $fuel->id)
            ->assertSet('editingComponent', true)
            ->assertSet('component_code', 'FUEL')
            ->assertSet('component_amount', '5000.00')
            ->set('component_amount', '6500.00')
            ->set('component_from', '2026-06-01')
            ->call('saveComponent')
            ->assertHasNoErrors()
            ->assertSet('showComponentForm', false)
            ->assertSee('6500.00');

        $this->assertSame(
            '6500.00',
            app(CompensationService::class)->componentsOn($employment, '2026-06-01')->sole()->amount
        );
    }

    private function compensationDefinition(): void
    {
        ApprovalDefinition::query()->create([
            'code' => 'COMPENSATION_CHANGE',
            'name' => 'Compensation change',
            'is_active' => true,
        ]);
    }
}
