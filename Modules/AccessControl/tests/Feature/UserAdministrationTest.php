<?php

namespace Modules\AccessControl\Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\AccessControl\Livewire\Roles\RolePermissions;
use Modules\AccessControl\Livewire\Users\UserList;
use Modules\Organisation\Models\Company;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UserAdministrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_view_permission_alone_cannot_create_or_edit_users(): void
    {
        $role = Role::findOrCreate('viewer-only');
        $role->syncPermissions(['access-control.view']);

        $user = User::factory()->allCompanies()->create(['must_change_password' => false]);
        $user->assignRole($role);

        $this->actingAs($user)
            ->get(route('access-control.users.index'))
            ->assertOk();

        Livewire::actingAs($user)
            ->test(UserList::class)
            ->set('formName', 'New Person')
            ->set('formEmail', 'new@example.com')
            ->set('formPassword', 'password12345')
            ->set('formRoles', ['hr-officer'])
            ->set('formAllCompanies', true)
            ->call('save')
            ->assertForbidden();
    }

    public function test_view_permission_alone_cannot_change_role_permissions(): void
    {
        $role = Role::findOrCreate('viewer-only');
        $role->syncPermissions(['access-control.view']);

        $user = User::factory()->allCompanies()->create(['must_change_password' => false]);
        $user->assignRole($role);

        $target = Role::findOrCreate('custom-role');

        Livewire::actingAs($user)
            ->test(RolePermissions::class, ['role' => $target->id])
            ->set('selected', ['org.view'])
            ->call('save')
            ->assertForbidden();
    }

    public function test_creating_a_user_is_audited_without_the_password(): void
    {
        $admin = User::factory()->allCompanies()->withRole('super-admin')->create([
            'must_change_password' => false,
        ]);

        Livewire::actingAs($admin)
            ->test(UserList::class)
            ->set('formName', 'Pat Manager')
            ->set('formEmail', 'pat@example.com')
            ->set('formPassword', 'password12345')
            ->set('formRoles', ['hr-officer'])
            ->set('formAllCompanies', true)
            ->call('save')
            ->assertSuccessful();

        $created = User::query()->where('email', 'pat@example.com')->sole();
        $log = AuditLog::query()
            ->where('auditable_type', User::class)
            ->where('auditable_id', $created->id)
            ->where('event', 'CREATED')
            ->sole();

        $this->assertArrayHasKey('email', $log->new_values);
        $this->assertArrayNotHasKey('password', $log->new_values ?? []);
    }

    public function test_role_and_company_changes_are_audited(): void
    {
        $company = Company::factory()->create(['code' => 'COA']);
        $admin = User::factory()->allCompanies()->withRole('super-admin')->create([
            'must_change_password' => false,
        ]);

        $target = User::factory()->forCompanies([$company])->withRole('hr-officer')->create([
            'must_change_password' => false,
        ]);

        Livewire::actingAs($admin)
            ->test(UserList::class)
            ->call('openEdit', $target->id)
            ->set('formRoles', ['hr-manager'])
            ->set('formAllCompanies', false)
            ->set('formCompanyIds', [(string) $company->id])
            ->call('save')
            ->assertSuccessful();

        $log = AuditLog::query()
            ->where('auditable_type', User::class)
            ->where('auditable_id', $target->id)
            ->where('event', 'UPDATED')
            ->whereJsonContains('old_values->roles', 'hr-officer')
            ->sole();

        $this->assertSame(['roles' => ['hr-officer']], $log->old_values);
        $this->assertSame(['roles' => ['hr-manager']], $log->new_values);
    }

    public function test_saving_without_changes_writes_no_audit_row(): void
    {
        $company = Company::factory()->create();
        $admin = User::factory()->allCompanies()->withRole('super-admin')->create([
            'must_change_password' => false,
        ]);

        $target = User::factory()->forCompanies([$company])->withRole('hr-officer')->create([
            'must_change_password' => false,
            'name' => 'Same Name',
            'email' => 'same@example.com',
        ]);

        $before = AuditLog::query()
            ->where('auditable_type', User::class)
            ->where('auditable_id', $target->id)
            ->count();

        Livewire::actingAs($admin)
            ->test(UserList::class)
            ->call('openEdit', $target->id)
            ->call('save')
            ->assertSuccessful();

        $after = AuditLog::query()
            ->where('auditable_type', User::class)
            ->where('auditable_id', $target->id)
            ->count();

        $this->assertSame($before, $after);
    }

    public function test_admin_cannot_remove_own_super_admin_role_or_deactivate_self(): void
    {
        $admin = User::factory()->allCompanies()->withRole('super-admin')->create([
            'must_change_password' => false,
        ]);

        Livewire::actingAs($admin)
            ->test(UserList::class)
            ->call('openEdit', $admin->id)
            ->set('formRoles', ['hr-officer'])
            ->call('save')
            ->assertForbidden();

        Livewire::actingAs($admin)
            ->test(UserList::class)
            ->call('openEdit', $admin->id)
            ->set('formIsActive', false)
            ->call('save')
            ->assertForbidden();

        $this->assertTrue($admin->fresh()->hasRole('super-admin'));
        $this->assertTrue($admin->fresh()->is_active);
    }
}
