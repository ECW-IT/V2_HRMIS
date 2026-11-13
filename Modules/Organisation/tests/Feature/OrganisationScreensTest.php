<?php

namespace Modules\Organisation\Tests\Feature;

use App\Models\User;
use App\Support\Company\CompanyContext;
use Database\Seeders\OrganisationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Organisation\Enums\OrgUnitType;
use Modules\Organisation\Livewire\BreakTypesIndex;
use Modules\Organisation\Livewire\EmployeeCategoriesIndex;
use Modules\Organisation\Livewire\GradesIndex;
use Modules\Organisation\Livewire\JobsIndex;
use Modules\Organisation\Livewire\OrgTree;
use Modules\Organisation\Models\BreakType;
use Modules\Organisation\Models\Company;
use Modules\Organisation\Models\EmployeeCategory;
use Modules\Organisation\Models\Grade;
use Modules\Organisation\Models\Location;
use Modules\Organisation\Services\OrgUnitService;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class OrganisationScreensTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Permission::findOrCreate('org.view');
        Permission::findOrCreate('org.manage');
        Role::findOrCreate('super-admin');
        Role::findOrCreate('hr-manager');
    }

    public function test_each_screen_needs_org_view(): void
    {
        $routes = [
            'org.locations.index',
            'org.units.index',
            'org.jobs.index',
            'org.categories.index',
            'org.break-types.index',
            'org.grades.index',
        ];

        $without = User::factory()->allCompanies()->create(['must_change_password' => false]);
        $with = User::factory()->allCompanies()->withPermissions('org.view')->create([
            'must_change_password' => false,
        ]);

        foreach ($routes as $route) {
            $this->actingAs($without)->get(route($route))->assertForbidden();
            $this->actingAs($with)->get(route($route))->assertOk();
        }
    }

    public function test_companies_screen_is_super_admin_only(): void
    {
        $manager = User::factory()->allCompanies()->create(['must_change_password' => false]);
        $manager->assignRole('hr-manager');
        $manager->givePermissionTo(['org.view', 'org.manage']);

        $admin = User::factory()->allCompanies()->create(['must_change_password' => false]);
        $admin->assignRole('super-admin');
        $admin->givePermissionTo(['org.view', 'org.manage']);

        $this->actingAs($manager)->get(route('org.companies.index'))->assertForbidden();
        $this->actingAs($admin)->get(route('org.companies.index'))->assertOk();
    }

    public function test_user_with_view_only_cannot_save(): void
    {
        $user = User::factory()->allCompanies()->withPermissions('org.view')->create([
            'must_change_password' => false,
        ]);

        Livewire::actingAs($user)
            ->test(JobsIndex::class)
            ->set('code', 'DRV')
            ->set('name', 'Driver')
            ->call('save')
            ->assertForbidden();

        Livewire::actingAs($user)
            ->test(EmployeeCategoriesIndex::class)
            ->set('code', 'FAC')
            ->set('name', 'Factory')
            ->call('save')
            ->assertForbidden();

        Livewire::actingAs($user)
            ->test(BreakTypesIndex::class)
            ->set('name', 'Tea')
            ->call('save')
            ->assertForbidden();
    }

    public function test_manager_can_create_and_toggle_break_type(): void
    {
        $user = User::factory()->allCompanies()->withPermissions('org.view', 'org.manage')->create([
            'must_change_password' => false,
        ]);

        Livewire::actingAs($user)
            ->test(BreakTypesIndex::class)
            ->call('openCreate')
            ->set('name', 'Tea')
            ->set('is_paid', false)
            ->call('save')
            ->assertHasNoErrors();

        $type = BreakType::query()->where('name', 'Tea')->first();
        $this->assertNotNull($type);
        $this->assertTrue($type->is_active);

        Livewire::actingAs($user)
            ->test(BreakTypesIndex::class)
            ->call('toggleActive', $type->id);

        $this->assertFalse($type->fresh()->is_active);
    }

    public function test_manager_can_create_edit_and_toggle_employee_category(): void
    {
        $user = User::factory()->allCompanies()->withPermissions('org.view', 'org.manage')->create([
            'must_change_password' => false,
        ]);

        Livewire::actingAs($user)
            ->test(EmployeeCategoriesIndex::class)
            ->call('openCreate')
            ->set('code', 'FAC')
            ->set('name', 'Factory')
            ->set('payroll_workdays', '26')
            ->set('payroll_workhours', '8')
            ->set('on_duty_time', '08:00')
            ->set('off_duty_time', '17:00')
            ->call('save')
            ->assertHasNoErrors();

        $category = EmployeeCategory::query()->where('code', 'FAC')->first();
        $this->assertNotNull($category);
        $this->assertSame('Factory', $category->name);
        $this->assertTrue($category->is_active);

        Livewire::actingAs($user)
            ->test(EmployeeCategoriesIndex::class)
            ->call('openEdit', $category->id)
            ->set('name', 'Factory shift')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('Factory shift', $category->fresh()->name);

        Livewire::actingAs($user)
            ->test(EmployeeCategoriesIndex::class)
            ->call('toggleActive', $category->id);

        $this->assertFalse($category->fresh()->is_active);
    }

    public function test_org_tree_lists_only_the_selected_companys_units(): void
    {
        $a = Company::factory()->create();
        $b = Company::factory()->create();
        $svc = app(OrgUnitService::class);

        $svc->create([
            'company_id' => $a->id,
            'code' => 'A-PROD',
            'name' => 'A Production',
            'type' => OrgUnitType::DEPARTMENT,
        ]);
        $svc->create([
            'company_id' => $b->id,
            'code' => 'B-PROD',
            'name' => 'B Production',
            'type' => OrgUnitType::DEPARTMENT,
        ]);

        $user = User::factory()->allCompanies()->withPermissions('org.view')->create([
            'must_change_password' => false,
        ]);

        $this->actingAs($user);
        CompanyContext::set($a->id);

        Livewire::actingAs($user)
            ->test(OrgTree::class)
            ->assertSee('A-PROD')
            ->assertDontSee('B-PROD');
    }

    public function test_grade_rank_must_be_unique(): void
    {
        Grade::factory()->create(['rank' => 1, 'code' => 'G1', 'name' => 'Grade 1']);

        $user = User::factory()->allCompanies()->withPermissions('org.view', 'org.manage')->create([
            'must_change_password' => false,
        ]);

        Livewire::actingAs($user)
            ->test(GradesIndex::class)
            ->call('openCreate')
            ->set('code', 'G2')
            ->set('name', 'Grade 2')
            ->set('rank', 1)
            ->call('save')
            ->assertHasErrors('rank');
    }

    public function test_seeder_can_run_twice(): void
    {
        $this->seed(OrganisationSeeder::class);
        $this->seed(OrganisationSeeder::class);

        $this->assertSame(2, Company::query()->count());
        $this->assertSame(2, Location::query()->count());
        $this->assertSame(4, Grade::query()->count());
    }
}
