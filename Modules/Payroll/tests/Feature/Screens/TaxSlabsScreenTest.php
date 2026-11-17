<?php

namespace Modules\Payroll\Tests\Feature\Screens;

use App\Models\User;
use App\Support\Company\CompanyContext;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Payroll\Livewire\TaxSlabsIndex;
use Modules\Payroll\Models\TaxSlab;
use Tests\Support\HrFixtures;
use Tests\TestCase;

class TaxSlabsScreenTest extends TestCase
{
    use HrFixtures;
    use RefreshDatabase;

    public function test_screen_needs_its_permission_and_saves_a_row(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $company = $this->company();
        $guest = User::factory()->forCompanies([$company])->create(['must_change_password' => false]);
        $this->actingAs($guest);
        $this->get(route('payroll.tax-slabs'))->assertForbidden();

        $this->loginAs('payroll-officer', $company);
        CompanyContext::set($company->id);
        $from = now()->addMonth()->startOfMonth()->toDateString();

        Livewire::test(TaxSlabsIndex::class)
            ->call('openCreate')
            ->set('effective_from', $from)
            ->set('slabs', [
                ['width' => '100000.00', 'percent' => '6.00'],
                ['width' => '', 'percent' => '18.00'],
            ])
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('showForm', false)
            ->assertSee('6.00')
            ->assertSee('18.00');

        $this->assertSame(2, TaxSlab::query()->whereDate('effective_from', $from)->count());
    }
}
