<?php

namespace Modules\Payroll\Tests\Feature\Screens;

use App\Models\User;
use App\Support\Company\CompanyContext;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Payroll\Livewire\CompanyPaySettingsIndex;
use Modules\Payroll\Models\CompanyPaySetting;
use Tests\Support\HrFixtures;
use Tests\TestCase;

class CompanyPaySettingsScreenTest extends TestCase
{
    use HrFixtures;
    use RefreshDatabase;

    public function test_screen_needs_its_permission_and_saves_a_row(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $company = $this->company();
        $guest = User::factory()->forCompanies([$company])->create(['must_change_password' => false]);
        $this->actingAs($guest);
        $this->get(route('payroll.company-pay-settings'))->assertForbidden();

        $this->loginAs('payroll-officer', $company);
        CompanyContext::set($company->id);
        $from = now()->addMonth()->startOfMonth()->toDateString();

        Livewire::test(CompanyPaySettingsIndex::class)
            ->call('openCreate')
            ->set('hours_per_month', '200')
            ->set('hours_per_day', '8')
            ->set('late_divisor', '480')
            ->set('attendance_min_offset', '0.0')
            ->set('attendance_max_offset', '1.0')
            ->set('ot_multiplier', '1.50')
            ->set('effective_from', $from)
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('showForm', false)
            ->assertSee('200');

        $this->assertTrue(CompanyPaySetting::query()->where('company_id', $company->id)->where('hours_per_month', 200)->exists());
    }
}
