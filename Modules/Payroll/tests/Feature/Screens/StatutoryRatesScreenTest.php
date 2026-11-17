<?php

namespace Modules\Payroll\Tests\Feature\Screens;

use App\Models\User;
use App\Support\Company\CompanyContext;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Payroll\Livewire\StatutoryRatesIndex;
use Modules\Payroll\Models\StatutoryRate;
use Tests\Support\HrFixtures;
use Tests\TestCase;

class StatutoryRatesScreenTest extends TestCase
{
    use HrFixtures;
    use RefreshDatabase;

    public function test_screen_needs_its_permission_and_saves_a_row(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $company = $this->company();
        $guest = User::factory()->forCompanies([$company])->create(['must_change_password' => false]);
        $this->actingAs($guest);
        $this->get(route('payroll.statutory-rates'))->assertForbidden();

        $this->loginAs('payroll-officer', $company);
        CompanyContext::set($company->id);
        $from = now()->addMonth()->startOfMonth()->toDateString();

        Livewire::test(StatutoryRatesIndex::class)
            ->call('openCreate')
            ->set('code', 'EPF_EE')
            ->set('percent', '8.00')
            ->set('effective_from', $from)
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('showForm', false)
            ->assertSee('EPF_EE')
            ->assertSee('8.00');

        $this->assertTrue(StatutoryRate::query()->where('code', 'EPF_EE')->whereDate('effective_from', $from)->exists());
    }
}
