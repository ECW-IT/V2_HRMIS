<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\AccessControl\Livewire\Roles\RoleList;
use Modules\AccessControl\Livewire\Roles\RolePermissions;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RolePermissionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Permission::findOrCreate('access-control.view');
        Permission::findOrCreate('access-control.manage');
        Permission::findOrCreate('org.view');
        Permission::findOrCreate('employee.view');
    }

    public function test_guest_is_redirected_from_roles(): void
    {
        $this->get(route('access-control.roles.index'))->assertRedirect('/login');
    }

    public function test_user_without_permission_is_forbidden(): void
    {
        $user = User::factory()->allCompanies()->create(['must_change_password' => false]);

        $this->actingAs($user)
            ->get(route('access-control.roles.index'))
            ->assertForbidden();
    }

    public function test_can_create_a_role(): void
    {
        $user = User::factory()->allCompanies()->withPermissions('access-control.view', 'access-control.manage')->create([
            'must_change_password' => false,
        ]);

        Livewire::actingAs($user)
            ->test(RoleList::class)
            ->call('openCreate')
            ->set('formName', 'custom-role')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertNotNull(Role::findByName('custom-role'));
    }

    public function test_can_sync_permissions_on_a_role(): void
    {
        $user = User::factory()->allCompanies()->withPermissions('access-control.view', 'access-control.manage')->create([
            'must_change_password' => false,
        ]);

        $role = Role::create(['name' => 'custom-role', 'guard_name' => 'web']);

        Livewire::actingAs($user)
            ->test(RolePermissions::class, ['role' => $role->id])
            ->set('selected', ['org.view', 'employee.view'])
            ->call('save')
            ->assertRedirect(route('access-control.roles.index'));

        $role->refresh();
        $this->assertTrue($role->hasPermissionTo('org.view'));
        $this->assertTrue($role->hasPermissionTo('employee.view'));
        $this->assertFalse($role->hasPermissionTo('access-control.view'));
    }

    public function test_cannot_delete_role_that_still_has_users(): void
    {
        $user = User::factory()->allCompanies()->withPermissions('access-control.view', 'access-control.manage')->create([
            'must_change_password' => false,
        ]);

        $role = Role::create(['name' => 'busy-role', 'guard_name' => 'web']);
        $user->assignRole($role);

        Livewire::actingAs($user)
            ->test(RoleList::class)
            ->call('delete', $role->id)
            ->assertHasErrors('delete');

        $this->assertNotNull(Role::findByName('busy-role'));
    }
}
