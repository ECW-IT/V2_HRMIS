<?php

namespace Modules\Payroll\Tests\Feature\Screens;

use App\Models\User;
use App\Support\Company\CompanyContext;
use Database\Seeders\ApprovalDefinitionSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Calendar\Models\PayPeriod;
use Modules\Payroll\Livewire\SalaryAdvancesIndex;
use Modules\Payroll\Models\SalaryAdvance;
use Tests\Support\HrFixtures;
use Tests\TestCase;

class SalaryAdvancesScreenTest extends TestCase
{
    use HrFixtures;
    use RefreshDatabase;

    public function test_screen_needs_its_permission_and_saves_a_row(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(ApprovalDefinitionSeeder::class);
        $company = $this->company();
        $guest = User::factory()->forCompanies([$company])->create(['must_change_password' => false]);
        $this->actingAs($guest);
        $this->get(route('payroll.advances'))->assertForbidden();

        $this->loginAs('payroll-officer', $company);
        CompanyContext::set($company->id);
        $employment = $this->hired('2026-01-01', $company);
        $period = PayPeriod::factory()->create([
            'company_id' => $company->id,
            'year' => 2026,
            'month' => 11,
            'start_date' => '2026-11-01',
            'end_date' => '2026-11-30',
        ]);

        Livewire::test(SalaryAdvancesIndex::class)
            ->call('openCreate')
            ->set('employmentId', (string) $employment->id)
            ->set('payPeriodId', (string) $period->id)
            ->set('amount', '5000.00')
            ->set('reason', 'Travel')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('showForm', false)
            ->assertSee('5000.00');

        $this->assertTrue(SalaryAdvance::query()->where('employment_id', $employment->id)->where('amount', '5000.00')->exists());
    }
}
