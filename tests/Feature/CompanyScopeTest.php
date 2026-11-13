<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Company\CompanyContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Organisation\Models\Company;
use Tests\Support\Probe\ScopedProbe;
use Tests\TestCase;

class CompanyScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_sees_only_permitted_company_rows(): void
    {
        [$a, $b] = Company::factory()->count(2)->create()->all();
        ScopedProbe::factory()->create(['company_id' => $a->id]);
        ScopedProbe::factory()->create(['company_id' => $b->id]);

        $this->actingAs(User::factory()->forCompanies([$a])->create());

        $this->assertSame(1, ScopedProbe::count());
        $this->assertSame($a->id, ScopedProbe::first()->company_id);
    }

    public function test_all_companies_user_sees_everything_until_one_is_selected(): void
    {
        [$a, $b] = Company::factory()->count(2)->create()->all();
        ScopedProbe::factory()->create(['company_id' => $a->id]);
        ScopedProbe::factory()->create(['company_id' => $b->id]);

        $this->actingAs(User::factory()->allCompanies()->create());
        $this->assertSame(2, ScopedProbe::count());

        CompanyContext::set($b->id);
        $this->assertSame(1, ScopedProbe::count());

        CompanyContext::set(null);
        $this->assertSame(2, ScopedProbe::count());
    }

    public function test_selecting_a_company_the_user_lacks_is_refused(): void
    {
        [$a, $b] = Company::factory()->count(2)->create()->all();
        $this->actingAs(User::factory()->forCompanies([$a])->create());

        $this->expectException(AuthorizationException::class);
        CompanyContext::set($b->id);
    }

    public function test_find_on_another_companys_row_returns_null(): void
    {
        [$a, $b] = Company::factory()->count(2)->create()->all();
        $other = ScopedProbe::factory()->create(['company_id' => $b->id]);

        $this->actingAs(User::factory()->forCompanies([$a])->create());
        $this->assertNull(ScopedProbe::find($other->id));
    }

    public function test_scope_is_not_applied_without_a_user(): void
    {
        $a = Company::factory()->create();
        ScopedProbe::factory()->count(2)->create(['company_id' => $a->id]);
        $this->assertSame(2, ScopedProbe::count());
    }

    public function test_inactive_company_is_not_permitted_for_all_companies_user(): void
    {
        $active = Company::factory()->create();
        $inactive = Company::factory()->create(['is_active' => false]);
        $this->actingAs(User::factory()->allCompanies()->create());
        $this->assertSame([$active->id], CompanyContext::permittedIds());
    }
}
