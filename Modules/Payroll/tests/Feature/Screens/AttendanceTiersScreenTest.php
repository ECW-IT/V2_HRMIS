<?php

namespace Modules\Payroll\Tests\Feature\Screens;

use App\Models\User;
use App\Support\Company\CompanyContext;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Payroll\Livewire\AttendanceTiersIndex;
use Modules\Payroll\Models\AttendanceAllowanceTier;
use Tests\Support\HrFixtures;
use Tests\TestCase;

class AttendanceTiersScreenTest extends TestCase
{
    use HrFixtures;
    use RefreshDatabase;

    public function test_screen_needs_its_permission_and_saves_a_row(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $company = $this->company();
        $guest = User::factory()->forCompanies([$company])->create(['must_change_password' => false]);
        $this->actingAs($guest);
        $this->get(route('payroll.attendance-tiers'))->assertForbidden();

        $this->loginAs('payroll-officer', $company);
        CompanyContext::set($company->id);
        $from = now()->addMonth()->startOfMonth()->toDateString();

        Livewire::test(AttendanceTiersIndex::class)
            ->call('openCreate')
            ->set('code', 'FULL')
            ->set('name', 'Full attendance')
            ->set('base_amount', '5000.00')
            ->set('per_day_rate', '250.00')
            ->set('effective_from', $from)
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('showForm', false)
            ->assertSee('FULL')
            ->assertSee('Full attendance');

        $this->assertTrue(AttendanceAllowanceTier::query()->where('code', 'FULL')->exists());
    }
}
