<?php

namespace Modules\Payroll\Tests\Feature\Screens;

use App\Models\User;
use App\Support\Company\CompanyContext;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Payroll\Database\Seeders\PayComponentSeeder;
use Modules\Payroll\Livewire\PayComponentsIndex;
use Modules\Payroll\Models\PayComponent;
use Tests\Support\HrFixtures;
use Tests\TestCase;

class PayComponentsScreenTest extends TestCase
{
    use HrFixtures;
    use RefreshDatabase;

    public function test_screen_needs_its_permission(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $company = $this->company();
        $user = User::factory()->forCompanies([$company])->create(['must_change_password' => false]);
        $this->actingAs($user);

        $this->get(route('payroll.components'))->assertForbidden();
    }

    public function test_screen_is_limited_to_the_selected_company(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $a = $this->company();
        $b = $this->company();
        $this->loginAs('payroll-officer', $a);
        CompanyContext::set($a->id);

        $this->get(route('payroll.components'))->assertOk();

        $this->expectException(AuthorizationException::class);
        CompanyContext::set($b->id);
    }

    public function test_lists_components_and_marks_system_rows(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(PayComponentSeeder::class);
        $company = $this->company();
        $this->loginAs('payroll-officer', $company);
        CompanyContext::set($company->id);

        Livewire::test(PayComponentsIndex::class)
            ->assertSee('BASIC')
            ->assertSee('System');
    }

    public function test_create_calls_the_service_and_shows_its_error(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $company = $this->company();
        $this->loginAs('payroll-officer', $company);
        CompanyContext::set($company->id);

        Livewire::test(PayComponentsIndex::class)
            ->call('openCreate')
            ->set('code', 'X2')
            ->set('name', 'Sheet two')
            ->set('kind', 'EARNING')
            ->set('method', 'FIXED')
            ->set('sheet', 2)
            ->set('epf_liable', true)
            ->set('taxable', true)
            ->set('display_order', 1)
            ->call('save')
            ->assertSee('A sheet 2 component cannot be EPF-liable.');

        $this->assertNull(PayComponent::query()->where('code', 'X2')->first());
    }

    public function test_write_buttons_need_payroll_config(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(PayComponentSeeder::class);
        $company = $this->company();
        $this->loginAs('auditor', $company);
        CompanyContext::set($company->id);

        $this->get(route('payroll.components'))->assertOk();

        Livewire::test(PayComponentsIndex::class)
            ->assertDontSee('New component')
            ->call('openCreate')
            ->assertForbidden();
    }
}
