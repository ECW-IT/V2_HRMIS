<?php

namespace Modules\Organisation\Tests\Feature;

use App\Models\User;
use App\Support\Company\CompanyContext;
use Database\Seeders\OrganisationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Organisation\Enums\OrgUnitType;
use Modules\Organisation\Livewire\DesignationsIndex;
use Modules\Organisation\Livewire\EmployeeCategoriesIndex;
use Modules\Organisation\Livewire\GradesIndex;
use Modules\Organisation\Livewire\OrgTree;
use Modules\Organisation\Models\Company;
use Modules\Organisation\Models\EmployeeCategory;
use Modules\Organisation\Models\Grade;
use Modules\Organisation\Models\Location;
use Modules\Organisation\Models\OrgUnit;
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
            'org.designations.index',
            'org.categories.index',
            'org.staff-work-categories.index',
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
            ->test(DesignationsIndex::class)
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
            ->set('level', 1)
            ->call('save')
            ->assertHasNoErrors();

        $category = EmployeeCategory::query()->where('code', 'FAC')->first();
        $this->assertNotNull($category);
        $this->assertSame('Factory', $category->name);
        $this->assertSame(1, $category->level);
        $this->assertTrue($category->is_active);

        Livewire::actingAs($user)
            ->test(EmployeeCategoriesIndex::class)
            ->call('openEdit', $category->id)
            ->set('name', 'Factory shift')
            ->set('level', 2)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('Factory shift', $category->fresh()->name);

        Livewire::actingAs($user)
            ->test(EmployeeCategoriesIndex::class)
            ->call('toggleActive', $category->id);

        $this->assertFalse($category->fresh()->is_active);
    }

    public function test_category_screen_shows_no_rule_fields(): void
    {
        $user = User::factory()->allCompanies()->withPermissions('org.view', 'org.manage')->create([
            'must_change_password' => false,
        ]);

        Livewire::actingAs($user)
            ->test(EmployeeCategoriesIndex::class)
            ->call('openCreate')
            ->assertDontSee('On duty')
            ->assertDontSee('OT rate')
            ->assertDontSee('Annual')
            ->assertDontSee('Break')
            ->assertSee('A category is a label');
    }

    public function test_break_types_page_is_gone(): void
    {
        $user = User::factory()->allCompanies()->withPermissions('org.view')->create([
            'must_change_password' => false,
        ]);

        $this->actingAs($user)->get('/organisation/break-types')->assertNotFound();
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

    public function test_org_tree_prompts_for_a_company_when_none_is_selected(): void
    {
        Company::factory()->count(2)->create();
        $user = User::factory()->allCompanies()->withPermissions('org.view', 'org.manage')->create([
            'must_change_password' => false,
        ]);

        $this->actingAs($user);
        $this->assertNull(CompanyContext::id());

        Livewire::actingAs($user)
            ->test(OrgTree::class)
            ->assertSee('Select a company first (use the company dropdown in the top bar).')
            ->assertDontSee('Add department');
    }

    public function test_org_tree_shows_add_department_when_a_company_is_selected(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->allCompanies()->withPermissions('org.view', 'org.manage')->create([
            'must_change_password' => false,
        ]);

        $this->actingAs($user);
        CompanyContext::set($company->id);

        Livewire::actingAs($user)
            ->test(OrgTree::class)
            ->assertDontSee('Select a company first (use the company dropdown in the top bar).')
            ->assertSee('Add department')
            ->assertSee('No departments yet.');
    }

    public function test_department_break_down_shows_departments_collapsed_by_default(): void
    {
        $company = Company::factory()->create();
        $units = app(OrgUnitService::class);
        $department = $units->create([
            'company_id' => $company->id,
            'code' => 'PROD',
            'name' => 'Production',
            'type' => OrgUnitType::DEPARTMENT,
        ]);
        $section = $units->create([
            'company_id' => $company->id,
            'parent_id' => $department->id,
            'code' => 'PROD_S',
            'name' => 'Production section',
            'type' => OrgUnitType::SECTION,
        ]);
        $units->create([
            'company_id' => $company->id,
            'parent_id' => $section->id,
            'code' => 'PROD_A',
            'name' => 'Production - A',
            'type' => OrgUnitType::SUBSECTION,
        ]);

        $user = User::factory()->allCompanies()->withPermissions('org.view', 'org.manage')->create([
            'must_change_password' => false,
        ]);

        $this->actingAs($user);
        CompanyContext::set($company->id);

        Livewire::actingAs($user)
            ->test(OrgTree::class)
            ->assertSee('Department break down')
            ->assertSee('Production')
            ->assertDontSee('Production section')
            ->assertDontSee('Production - A')
            ->assertSeeHtml('aria-label="Edit"')
            ->assertSeeHtml('aria-label="Add section"')
            ->assertSeeHtml('backdrop-blur-sm')
            ->assertSeeHtml('bg-white/70')
            ->assertSeeHtml('border-green-600')
            ->assertSeeHtml('border-sky-500')
            ->assertSeeHtml('border-slate-900')
            ->assertSeeHtml('border-red-600')
            ->call('toggleExpanded', $department->id)
            ->assertSee('Production section')
            ->assertDontSee('Production - A')
            ->assertSeeHtml('aria-label="Add sub section"')
            ->call('toggleExpanded', $section->id)
            ->assertSee('Production - A');
    }

    public function test_clearing_search_collapses_back_to_departments_only(): void
    {
        $company = Company::factory()->create();
        $units = app(OrgUnitService::class);
        $department = $units->create([
            'company_id' => $company->id,
            'code' => 'PROD',
            'name' => 'Production',
            'type' => OrgUnitType::DEPARTMENT,
        ]);
        $section = $units->create([
            'company_id' => $company->id,
            'parent_id' => $department->id,
            'code' => 'PROD_S',
            'name' => 'Production section',
            'type' => OrgUnitType::SECTION,
        ]);
        $units->create([
            'company_id' => $company->id,
            'parent_id' => $section->id,
            'code' => 'PROD_A',
            'name' => 'Production - A',
            'type' => OrgUnitType::SUBSECTION,
        ]);

        $user = User::factory()->allCompanies()->withPermissions('org.view')->create([
            'must_change_password' => false,
        ]);

        $this->actingAs($user);
        CompanyContext::set($company->id);

        Livewire::actingAs($user)
            ->test(OrgTree::class)
            ->set('search', 'Production - A')
            ->assertSee('Production - A')
            ->set('search', '')
            ->assertSee('Production')
            ->assertDontSee('Production section')
            ->assertDontSee('Production - A');
    }

    public function test_move_unit_new_parent_is_searchable_and_can_save(): void
    {
        $company = Company::factory()->create();
        $units = app(OrgUnitService::class);
        $departmentA = $units->create([
            'company_id' => $company->id,
            'code' => 'HR',
            'name' => 'Human Resources',
            'type' => OrgUnitType::DEPARTMENT,
        ]);
        $departmentB = $units->create([
            'company_id' => $company->id,
            'code' => 'FIN',
            'name' => 'Finance',
            'type' => OrgUnitType::DEPARTMENT,
        ]);
        $section = $units->create([
            'company_id' => $company->id,
            'parent_id' => $departmentA->id,
            'code' => 'HR_S',
            'name' => 'Human Resources',
            'type' => OrgUnitType::SECTION,
        ]);
        $financeSection = $units->create([
            'company_id' => $company->id,
            'parent_id' => $departmentB->id,
            'code' => 'FIN_S',
            'name' => 'Finance section',
            'type' => OrgUnitType::SECTION,
        ]);
        $units->create([
            'company_id' => $company->id,
            'parent_id' => $financeSection->id,
            'code' => 'FIN_A',
            'name' => 'Finance - A',
            'type' => OrgUnitType::SUBSECTION,
        ]);

        $user = User::factory()->allCompanies()->withPermissions('org.view', 'org.manage')->create([
            'must_change_password' => false,
        ]);

        $this->actingAs($user);
        CompanyContext::set($company->id);

        Livewire::actingAs($user)
            ->test(OrgTree::class)
            ->call('openMove', $section->id)
            ->assertSee('Move unit')
            ->assertSee('New parent')
            ->assertSeeHtml('placeholder="Search parent"')
            ->assertSeeHtml('aria-label="Search parent"')
            ->assertSee('(DEPARTMENT)')
            ->assertSee('(SECTION)')
            ->assertSee('(SUBSECTION)')
            ->set('moveParentId', $departmentB->id)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame($departmentB->id, $section->fresh()->parent_id);
    }

    public function test_manager_can_create_edit_and_deactivate_org_unit_chain(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->allCompanies()->withPermissions('org.view', 'org.manage')->create([
            'must_change_password' => false,
        ]);

        $this->actingAs($user);
        CompanyContext::set($company->id);

        Livewire::actingAs($user)
            ->test(OrgTree::class)
            ->call('openAddDepartment')
            ->set('code', 'PROD')
            ->set('name', 'Production')
            ->call('save')
            ->assertHasNoErrors();

        $department = OrgUnit::query()->where('code', 'PROD')->first();
        $this->assertNotNull($department);
        $this->assertSame(OrgUnitType::DEPARTMENT, $department->type);

        Livewire::actingAs($user)
            ->test(OrgTree::class)
            ->call('openAddChild', $department->id)
            ->assertSet('type', OrgUnitType::SECTION->value)
            ->set('code', 'PROD_S')
            ->set('name', 'Production')
            ->call('save')
            ->assertHasNoErrors();

        $section = OrgUnit::query()->where('code', 'PROD_S')->first();
        $this->assertNotNull($section);
        $this->assertSame($department->id, $section->parent_id);

        Livewire::actingAs($user)
            ->test(OrgTree::class)
            ->call('openAddChild', $section->id)
            ->assertSet('type', OrgUnitType::SUBSECTION->value)
            ->set('code', 'PROD_A')
            ->set('name', 'Production - A')
            ->call('save')
            ->assertHasNoErrors();

        $subSection = OrgUnit::query()->where('code', 'PROD_A')->first();
        $this->assertNotNull($subSection);
        $this->assertSame($section->id, $subSection->parent_id);

        Livewire::actingAs($user)
            ->test(OrgTree::class)
            ->call('openEdit', $subSection->id)
            ->set('name', 'Production line A')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('Production line A', $subSection->fresh()->name);

        Livewire::actingAs($user)
            ->test(OrgTree::class)
            ->call('deactivate', $subSection->id)
            ->assertHasNoErrors();

        $this->assertFalse($subSection->fresh()->is_active);

        Livewire::actingAs($user)
            ->test(OrgTree::class)
            ->call('activate', $subSection->id);

        $this->assertTrue($subSection->fresh()->is_active);
    }

    public function test_view_only_user_cannot_create_org_unit(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->allCompanies()->withPermissions('org.view')->create([
            'must_change_password' => false,
        ]);

        $this->actingAs($user);
        CompanyContext::set($company->id);

        Livewire::actingAs($user)
            ->test(OrgTree::class)
            ->call('openAddDepartment')
            ->assertForbidden();
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
