<?php

namespace Modules\CoreHr\Tests\Feature;

use App\Models\User;
use App\Support\Company\CompanyContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Modules\CoreHr\Database\Seeders\DocumentTypeSeeder;
use Modules\CoreHr\Livewire\ReadinessDashboard;
use Modules\CoreHr\Models\EmployeeBankAccount;
use Spatie\Permission\Models\Permission;
use Tests\Support\HrFixtures;
use Tests\TestCase;

class ReadinessDashboardTest extends TestCase
{
    use HrFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('private');
        $this->seed(DocumentTypeSeeder::class);
        Permission::findOrCreate('employee.view');
    }

    public function test_dashboard_needs_employee_view(): void
    {
        $c = $this->company();
        $user = User::factory()->forCompanies([$c])->create(['must_change_password' => false]);

        $this->actingAs($user)->get(route('employees.readiness'))->assertForbidden();

        $allowed = User::factory()
            ->forCompanies([$c])
            ->withPermissions('employee.view')
            ->create(['must_change_password' => false]);

        $this->actingAs($allowed)->get(route('employees.readiness'))->assertOk();
    }

    public function test_dashboard_shows_counts_for_the_selected_company(): void
    {
        $c = $this->company();
        $e = $this->hired('2026-01-01', $c);
        EmployeeBankAccount::where('employment_id', $e->id)->delete();

        $user = User::factory()
            ->forCompanies([$c])
            ->withPermissions('employee.view')
            ->create(['must_change_password' => false]);

        $this->actingAs($user);
        CompanyContext::set($c->id);

        $component = Livewire::actingAs($user)
            ->test(ReadinessDashboard::class)
            ->set('asOf', '2026-11-01')
            ->assertSee('HR004')
            ->assertSee('Blockers');

        $sections = $component->viewData('sections');
        $this->assertSame(1, $sections[0]['summary']['HR004']['count']);
        $this->assertGreaterThanOrEqual(1, $component->viewData('totalBlockers'));
        $component->assertSee((string) $component->viewData('totalBlockers'));
    }

    public function test_clicking_a_check_lists_the_failing_employees(): void
    {
        $c = $this->company();
        $e = $this->hired('2026-01-01', $c);
        EmployeeBankAccount::where('employment_id', $e->id)->delete();

        $user = User::factory()
            ->forCompanies([$c])
            ->withPermissions('employee.view')
            ->create(['must_change_password' => false]);

        $this->actingAs($user);
        CompanyContext::set($c->id);

        Livewire::actingAs($user)
            ->test(ReadinessDashboard::class)
            ->set('asOf', '2026-11-01')
            ->call('show', 'HR004')
            ->assertSee($e->employee_no);
    }
}
