<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Organisation\Livewire\CompaniesIndex;
use Modules\Organisation\Models\Company;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CompaniesIndexTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Permission::findOrCreate('org.view');
        Permission::findOrCreate('org.manage');
        Role::findOrCreate('super-admin');
    }

    public function test_guest_is_redirected_from_companies(): void
    {
        $this->get(route('org.companies.index'))->assertRedirect('/login');
    }

    public function test_super_admin_with_org_view_can_open_companies(): void
    {
        $user = User::factory()->allCompanies()->create(['must_change_password' => false]);
        $user->assignRole('super-admin');
        $user->givePermissionTo(['org.view', 'org.manage']);

        $this->actingAs($user)
            ->get(route('org.companies.index'))
            ->assertOk();
    }

    public function test_non_super_admin_is_forbidden_from_companies(): void
    {
        $user = User::factory()->allCompanies()->withPermissions('org.view', 'org.manage')->create([
            'must_change_password' => false,
        ]);

        $this->actingAs($user)
            ->get(route('org.companies.index'))
            ->assertForbidden();
    }

    public function test_super_admin_can_create_a_company(): void
    {
        $user = User::factory()->allCompanies()->create(['must_change_password' => false]);
        $user->assignRole('super-admin');
        $user->givePermissionTo(['org.view', 'org.manage']);

        Livewire::actingAs($user)
            ->test(CompaniesIndex::class)
            ->call('openCreate')
            ->set('formCode', 'CO1')
            ->set('formName', 'Company One')
            ->set('formPrefix', 'ECW')
            ->set('formPadding', 3)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('companies', [
            'code' => 'CO1',
            'name' => 'Company One',
            'employee_no_prefix' => 'ECW',
        ]);
    }

    public function test_user_without_org_manage_cannot_create_company(): void
    {
        $user = User::factory()->allCompanies()->create(['must_change_password' => false]);
        $user->assignRole('super-admin');
        $user->givePermissionTo(['org.view']);

        Livewire::actingAs($user)
            ->test(CompaniesIndex::class)
            ->call('openCreate')
            ->assertForbidden();
    }

    public function test_super_admin_can_update_and_deactivate_company(): void
    {
        $user = User::factory()->allCompanies()->create(['must_change_password' => false]);
        $user->assignRole('super-admin');
        $user->givePermissionTo(['org.view', 'org.manage']);

        $company = Company::factory()->create([
            'code' => 'CO1',
            'name' => 'Company One',
            'employee_no_prefix' => 'ECW',
            'is_active' => true,
        ]);

        Livewire::actingAs($user)
            ->test(CompaniesIndex::class)
            ->call('openEdit', $company->id)
            ->set('formName', 'Company One Updated')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('Company One Updated', $company->fresh()->name);

        Livewire::actingAs($user)
            ->test(CompaniesIndex::class)
            ->call('toggleActive', $company->id);

        $this->assertFalse($company->fresh()->is_active);
    }
}
