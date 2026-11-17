<?php

namespace Modules\Payroll\Tests\Feature\Screens;

use App\Models\User;
use App\Support\Company\CompanyContext;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Payroll\Livewire\LoanTypesIndex;
use Modules\Payroll\Models\LoanType;
use Tests\Support\HrFixtures;
use Tests\TestCase;

class LoanTypesScreenTest extends TestCase
{
    use HrFixtures;
    use RefreshDatabase;

    public function test_screen_needs_its_permission_and_saves_a_row(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $company = $this->company();
        $guest = User::factory()->forCompanies([$company])->create(['must_change_password' => false]);
        $this->actingAs($guest);
        $this->get(route('payroll.loan-types'))->assertForbidden();

        $this->loginAs('payroll-officer', $company);
        CompanyContext::set($company->id);

        $this->get(route('payroll.loan-types'))->assertOk();

        Livewire::test(LoanTypesIndex::class)
            ->call('openCreate')
            ->set('code', 'WELFARE')
            ->set('name', 'Welfare loan')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('showForm', false)
            ->assertSee('WELFARE');

        $this->assertNotNull(LoanType::query()->where('code', 'WELFARE')->first());
    }
}
