<?php

namespace Modules\Payroll\Tests\Feature\Screens;

use App\Models\User;
use App\Support\Company\CompanyContext;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Organisation\Models\EmployeeCategory;
use Modules\Payroll\Livewire\CategoryPayRulesIndex;
use Modules\Payroll\Models\CategoryPayRule;
use Tests\Support\HrFixtures;
use Tests\TestCase;

class GradePayRulesScreenTest extends TestCase
{
    use HrFixtures;
    use RefreshDatabase;

    public function test_screen_needs_its_permission_and_saves_a_row(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $company = $this->company();
        $guest = User::factory()->forCompanies([$company])->create(['must_change_password' => false]);
        $this->actingAs($guest);
        $this->get(route('payroll.category-pay-rules'))->assertForbidden();

        $this->loginAs('payroll-officer', $company);
        CompanyContext::set($company->id);
        $category = EmployeeCategory::factory()->create(['code' => 'GSCREEN', 'name' => 'Screen category']);
        $from = now()->addMonth()->startOfMonth()->toDateString();

        Livewire::test(CategoryPayRulesIndex::class)
            ->call('openCreate')
            ->set('employee_category_id', (string) $category->id)
            ->set('extra_day_method', 'DAILY')
            ->set('effective_from', $from)
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('showForm', false)
            ->assertSee('GSCREEN')
            ->assertSee('DAILY');

        $this->assertTrue(CategoryPayRule::query()->where('employee_category_id', $category->id)->exists());
    }
}
