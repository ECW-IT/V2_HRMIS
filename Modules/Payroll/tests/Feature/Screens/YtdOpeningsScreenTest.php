<?php

namespace Modules\Payroll\Tests\Feature\Screens;

use App\Models\User;
use App\Support\Company\CompanyContext;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Payroll\Livewire\YtdOpeningsIndex;
use Modules\Payroll\Models\YtdOpening;
use Tests\Support\HrFixtures;
use Tests\TestCase;

class YtdOpeningsScreenTest extends TestCase
{
    use HrFixtures;
    use RefreshDatabase;

    public function test_screen_needs_its_permission_and_saves_a_row(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $company = $this->company();
        $guest = User::factory()->forCompanies([$company])->create(['must_change_password' => false]);
        $this->actingAs($guest);
        $this->get(route('payroll.ytd-openings'))->assertForbidden();

        $this->loginAs('payroll-officer', $company);
        CompanyContext::set($company->id);
        $employment = $this->hired('2026-01-01', $company);

        Livewire::test(YtdOpeningsIndex::class)
            ->call('openCreate')
            ->set('employmentId', (string) $employment->id)
            ->set('tax_year_start', '2026-04-01')
            ->set('months_covered', '0')
            ->set('gross', '1200.00')
            ->set('taxable_pay', '1000.00')
            ->set('tax', '0.00')
            ->set('epf_employee', '80.00')
            ->set('epf_employer', '120.00')
            ->set('etf', '30.00')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('showForm', false)
            ->assertSee('1200.00');

        $this->assertTrue(YtdOpening::query()->where('employment_id', $employment->id)->where('gross', '1200.00')->exists());
    }
}
