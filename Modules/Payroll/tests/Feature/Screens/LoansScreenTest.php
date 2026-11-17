<?php

namespace Modules\Payroll\Tests\Feature\Screens;

use App\Models\User;
use App\Support\Company\CompanyContext;
use Database\Seeders\ApprovalDefinitionSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Payroll\Database\Seeders\LoanTypeSeeder;
use Modules\Payroll\Livewire\LoansIndex;
use Modules\Payroll\Models\Loan;
use Tests\Support\HrFixtures;
use Tests\TestCase;

class LoansScreenTest extends TestCase
{
    use HrFixtures;
    use RefreshDatabase;

    public function test_screen_needs_its_permission_and_saves_a_row(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(ApprovalDefinitionSeeder::class);
        $this->seed(LoanTypeSeeder::class);
        $company = $this->company();
        $guest = User::factory()->forCompanies([$company])->create(['must_change_password' => false]);
        $this->actingAs($guest);
        $this->get(route('payroll.loans'))->assertForbidden();

        $this->loginAs('payroll-officer', $company);
        CompanyContext::set($company->id);
        $employment = $this->hired('2026-01-01', $company);

        Livewire::test(LoansIndex::class)
            ->call('openCreate')
            ->set('employmentId', (string) $employment->id)
            ->set('loan_type_code', 'STAFF')
            ->set('amount', '10000.00')
            ->set('instalments', '3')
            ->set('first_month', now()->addMonth()->startOfMonth()->toDateString())
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('showForm', false)
            ->assertSee('10000.00');

        $this->assertTrue(Loan::query()->where('employment_id', $employment->id)->where('amount', '10000.00')->exists());
    }
}
