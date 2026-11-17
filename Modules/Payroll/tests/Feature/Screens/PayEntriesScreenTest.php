<?php

namespace Modules\Payroll\Tests\Feature\Screens;

use App\Models\User;
use App\Support\Company\CompanyContext;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Calendar\Models\PayPeriod;
use Modules\Payroll\Enums\CalcMethod;
use Modules\Payroll\Livewire\PayEntriesIndex;
use Modules\Payroll\Models\PayComponent;
use Modules\Payroll\Models\PayEntry;
use Tests\Support\HrFixtures;
use Tests\TestCase;

class PayEntriesScreenTest extends TestCase
{
    use HrFixtures;
    use RefreshDatabase;

    public function test_screen_needs_its_permission_and_saves_a_row(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $company = $this->company();
        $guest = User::factory()->forCompanies([$company])->create(['must_change_password' => false]);
        $this->actingAs($guest);
        $this->get(route('payroll.pay-entries'))->assertForbidden();

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
        PayComponent::factory()->create([
            'code' => 'BONUSX',
            'name' => 'Bonus entry',
            'method' => CalcMethod::ENTRY,
        ]);

        Livewire::test(PayEntriesIndex::class)
            ->call('openCreate')
            ->set('payPeriodId', (string) $period->id)
            ->set('employmentId', (string) $employment->id)
            ->set('component_code', 'BONUSX')
            ->set('amount', '1500.00')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('showForm', false)
            ->assertSee('BONUSX')
            ->assertSee('1500.00');

        $this->assertTrue(PayEntry::query()->where('employment_id', $employment->id)->where('amount', '1500.00')->exists());
    }
}
