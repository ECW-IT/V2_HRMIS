<?php

namespace Tests\Feature;

use App\Livewire\CompanySelector;
use App\Models\User;
use App\Support\Company\CompanyContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Calendar\Livewire\HolidaysIndex;
use Modules\Organisation\Models\Company;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class CompanySelectorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Permission::findOrCreate('org.view');
    }

    public function test_sole_permitted_company_is_auto_selected_on_holidays(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->forCompanies([$company])->withPermissions('org.view')->create([
            'must_change_password' => false,
        ]);

        $this->actingAs($user);
        $this->assertNull(CompanyContext::id());

        Livewire::actingAs($user)
            ->test(HolidaysIndex::class)
            ->assertDontSee('Select a company first (use the company dropdown in the top bar)');

        $this->assertSame($company->id, CompanyContext::id());
    }

    public function test_all_companies_still_requires_an_explicit_choice_when_several_exist(): void
    {
        Company::factory()->count(2)->create();
        $user = User::factory()->allCompanies()->withPermissions('org.view')->create([
            'must_change_password' => false,
        ]);

        $this->actingAs($user);

        Livewire::actingAs($user)
            ->test(HolidaysIndex::class)
            ->assertSee('Select a company first (use the company dropdown in the top bar)');

        $this->assertNull(CompanyContext::id());
    }

    public function test_company_selector_auto_selects_sole_company(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->forCompanies([$company])->create([
            'must_change_password' => false,
        ]);

        $this->actingAs($user);

        Livewire::actingAs($user)
            ->test(CompanySelector::class)
            ->assertSet('selectedCompanyId', $company->id);

        $this->assertSame($company->id, CompanyContext::id());
    }
}
