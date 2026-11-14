<?php

namespace Modules\Organisation\Tests\Feature;

use App\Models\User;
use App\Support\Company\CompanyContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Modules\CoreHr\Livewire\Tabs\Summary;
use Modules\CoreHr\Services\EmploymentService;
use Modules\Organisation\Database\Seeders\StaffWorkCategorySeeder;
use Modules\Organisation\Livewire\StaffWorkCategoriesIndex;
use Modules\Organisation\Models\StaffWorkCategory;
use Modules\Organisation\Services\StaffWorkCategoryService;
use Spatie\Permission\Models\Permission;
use Tests\Support\HrFixtures;
use Tests\TestCase;

class StaffWorkCategoryTest extends TestCase
{
    use HrFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Permission::findOrCreate('org.view');
        Permission::findOrCreate('org.manage');
        Permission::findOrCreate('employee.view');
        Permission::findOrCreate('employee.update');
        Permission::findOrCreate('employee.create');
    }

    public function test_seeder_creates_office_staff_and_shop_and_office(): void
    {
        $this->seed(StaffWorkCategorySeeder::class);

        $this->assertTrue(StaffWorkCategory::query()->where('code', 'OFF')->where('name', 'Office Staff')->exists());
        $this->assertTrue(StaffWorkCategory::query()->where('code', 'SHO')->where('name', 'Shop and Office')->exists());
    }

    public function test_master_screen_needs_org_view_and_write_needs_manage(): void
    {
        $without = User::factory()->allCompanies()->create(['must_change_password' => false]);
        $viewer = User::factory()->allCompanies()->withPermissions('org.view')->create(['must_change_password' => false]);

        $this->actingAs($without)->get(route('org.staff-work-categories.index'))->assertForbidden();
        $this->actingAs($viewer)->get(route('org.staff-work-categories.index'))->assertOk();

        Livewire::actingAs($viewer)
            ->test(StaffWorkCategoriesIndex::class)
            ->set('code', 'X1')
            ->set('name', 'Extra')
            ->call('save')
            ->assertForbidden();
    }

    public function test_can_create_update_and_deactivate_a_category(): void
    {
        $user = User::factory()->allCompanies()->withPermissions('org.view', 'org.manage')->create([
            'must_change_password' => false,
        ]);

        Livewire::actingAs($user)
            ->test(StaffWorkCategoriesIndex::class)
            ->call('openCreate')
            ->set('code', 'fld')
            ->set('name', 'Field Staff')
            ->call('save')
            ->assertHasNoErrors();

        $category = StaffWorkCategory::query()->where('code', 'FLD')->sole();
        $this->assertSame('Field Staff', $category->name);

        Livewire::actingAs($user)
            ->test(StaffWorkCategoriesIndex::class)
            ->call('openEdit', $category->id)
            ->set('name', 'Field Team')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('Field Team', $category->fresh()->name);

        Livewire::actingAs($user)
            ->test(StaffWorkCategoriesIndex::class)
            ->call('deactivate', $category->id)
            ->assertSuccessful();

        $this->assertFalse($category->fresh()->is_active);
    }

    public function test_codes_must_be_unique(): void
    {
        StaffWorkCategory::factory()->create(['code' => 'OFF', 'name' => 'Office Staff']);

        $this->expectException(ValidationException::class);
        app(StaffWorkCategoryService::class)->create([
            'code' => 'off',
            'name' => 'Duplicate',
        ]);
    }

    public function test_hire_stores_staff_work_category_id(): void
    {
        $company = $this->company();
        $category = StaffWorkCategory::factory()->create(['code' => 'OFF', 'name' => 'Office Staff']);

        $employment = app(EmploymentService::class)->hire(
            $this->person(),
            $company,
            $this->emp(['hire_date' => '2026-01-01', 'staff_work_category_id' => $category->id]),
            $this->asg($company),
        );

        $this->assertSame($category->id, $employment->staff_work_category_id);
        $this->assertSame('Office Staff', $employment->staffWorkCategory->name);
    }

    public function test_summary_shows_and_updates_office_shop_category(): void
    {
        $company = $this->company();
        $category = StaffWorkCategory::factory()->create(['code' => 'OFF', 'name' => 'Office Staff']);
        $employment = $this->hired('2026-01-01', $company);

        $user = User::factory()->forCompanies([$company])->withPermissions('employee.view', 'employee.update')->create([
            'must_change_password' => false,
        ]);
        $this->actingAs($user);
        CompanyContext::set($company->id);

        Livewire::actingAs($user)
            ->test(Summary::class, ['employment' => $employment])
            ->assertSee('Office / shop category')
            ->set('staffWorkCategoryId', $category->id)
            ->call('saveStaffWorkCategory')
            ->assertSuccessful()
            ->assertSee('Office / shop category updated.');

        $this->assertSame($category->id, $employment->fresh()->staff_work_category_id);
    }
}
