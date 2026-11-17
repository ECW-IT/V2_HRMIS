<?php

namespace Modules\Payroll\Tests\Feature\Screens;

use App\Models\User;
use App\Support\Company\CompanyContext;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Payroll\Livewire\CompensationIndex;
use Modules\Payroll\Models\EmployeeCompensation;
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
}
