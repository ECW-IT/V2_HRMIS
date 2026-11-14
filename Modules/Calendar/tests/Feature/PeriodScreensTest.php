<?php

namespace Modules\Calendar\Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use App\Support\Company\CompanyContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Calendar\Livewire\ExpectedDaysGrid;
use Modules\Calendar\Livewire\PayPeriodsIndex;
use Modules\Calendar\Services\ExpectedDaysService;
use Modules\Calendar\Services\PayPeriodService;
use Modules\Organisation\Models\Company;
use Modules\Organisation\Models\Grade;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class PeriodScreensTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Permission::findOrCreate('org.view');
        Permission::findOrCreate('period.manage');
    }

    public function test_grid_needs_period_manage_to_save(): void
    {
        $company = Company::factory()->create();
        $grade = Grade::factory()->create();
        $this->travelTo('2026-12-15');
        $period = app(PayPeriodService::class)->ensure($company, 2026, 12);

        $viewer = User::factory()->allCompanies()->withPermissions('org.view')->create([
            'must_change_password' => false,
        ]);
        $this->actingAs($viewer);
        CompanyContext::set($company->id);

        Livewire::actingAs($viewer)
            ->test(ExpectedDaysGrid::class)
            ->set('cells.'.$period->id.':'.$grade->id, '24')
            ->call('saveCell', $period->id, $grade->id)
            ->assertForbidden();

        $manager = User::factory()->allCompanies()->withPermissions('org.view', 'period.manage')->create([
            'must_change_password' => false,
        ]);
        $this->actingAs($manager);
        CompanyContext::set($company->id);

        Livewire::actingAs($manager)
            ->test(ExpectedDaysGrid::class)
            ->set('cells.'.$period->id.':'.$grade->id, '24')
            ->call('saveCell', $period->id, $grade->id)
            ->assertHasNoErrors();

        $this->assertSame(24.0, app(ExpectedDaysService::class)->for($period, $grade->id));
    }

    public function test_closed_period_cells_are_read_only(): void
    {
        $company = Company::factory()->create();
        $grade = Grade::factory()->create();
        $pp = app(PayPeriodService::class);
        $expected = app(ExpectedDaysService::class);

        $this->travelTo('2026-12-15');
        $period = $pp->ensure($company, 2026, 12);
        $expected->set($period, $grade, 24);

        $this->travelTo('2027-01-05');
        $period = $pp->markClosed($period->fresh(), null);

        $user = User::factory()->allCompanies()->withPermissions('org.view', 'period.manage')->create([
            'must_change_password' => false,
        ]);
        $this->actingAs($user);
        CompanyContext::set($company->id);

        Livewire::actingAs($user)
            ->test(ExpectedDaysGrid::class)
            ->set('cells.'.$period->id.':'.$grade->id, '20')
            ->call('saveCell', $period->id, $grade->id)
            ->assertSet('error', 'The period is closed.');

        $this->assertSame(24.0, $expected->for($period, $grade->id));
    }

    public function test_missing_expected_days_are_flagged_on_the_periods_list(): void
    {
        $this->travelTo('2026-12-15');
        $company = Company::factory()->create();
        Grade::factory()->count(2)->create();
        app(PayPeriodService::class)->ensure($company, 2026, 12);

        $user = User::factory()->allCompanies()->withPermissions('org.view')->create([
            'must_change_password' => false,
        ]);
        $this->actingAs($user);
        CompanyContext::set($company->id);

        Livewire::actingAs($user)
            ->test(PayPeriodsIndex::class)
            ->assertSee('Expected days: 0 of 2 set');
    }

    public function test_late_grace_can_be_changed_and_is_audited(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->allCompanies()->withPermissions('org.view', 'period.manage')->create([
            'must_change_password' => false,
        ]);
        $this->actingAs($user);
        CompanyContext::set($company->id);

        Livewire::actingAs($user)
            ->test(ExpectedDaysGrid::class)
            ->set('monthly_late_grace_minutes', 180)
            ->call('saveLateGrace')
            ->assertHasNoErrors();

        $this->assertSame(180, $company->fresh()->monthly_late_grace_minutes);
        $this->assertSame(1, AuditLog::query()
            ->where('auditable_type', Company::class)
            ->where('auditable_id', $company->id)
            ->where('event', 'UPDATED')
            ->count());
    }
}
