<?php

namespace Modules\Payroll\Tests\Feature\Screens;

use App\Models\User;
use App\Support\Company\CompanyContext;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Calendar\Enums\PeriodStatus;
use Modules\Calendar\Models\PayPeriod;
use Modules\CoreHr\Models\Employment;
use Modules\Organisation\Models\Company;
use Modules\Payroll\Enums\PayRunStatus;
use Modules\Payroll\Livewire\PayRunShow;
use Modules\Payroll\Livewire\PayRunsIndex;
use Modules\Payroll\Models\PayRun;
use Modules\Payroll\Models\PayRunEmployee;
use Modules\Payroll\Models\PayRunOverride;
use Tests\Support\HrFixtures;
use Tests\TestCase;

class PayRunScreenTest extends TestCase
{
    use HrFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_a_user_without_a_pay_run_permission_is_forbidden(): void
    {
        $company = $this->company();
        $run = $this->runFor($company);

        $guest = User::factory()->forCompanies([$company])->create(['must_change_password' => false]);
        $this->actingAs($guest);

        $this->get(route('payroll.pay-runs'))->assertForbidden();
        $this->get(route('payroll.pay-runs.show', $run))->assertForbidden();
    }

    public function test_a_payroll_officer_opens_one_draft_run_for_a_period(): void
    {
        $company = $this->company();
        $this->loginAs('payroll-officer', $company);
        CompanyContext::set($company->id);

        $period = PayPeriod::factory()->create([
            'company_id' => $company->id,
            'year' => 2026,
            'month' => 12,
            'start_date' => '2026-12-01',
            'end_date' => '2026-12-31',
            'status' => PeriodStatus::OPEN,
        ]);

        Livewire::test(PayRunsIndex::class)
            ->call('openCreate')
            ->set('payPeriodId', (string) $period->id)
            ->call('openRun')
            ->assertHasNoErrors()
            ->assertSet('showForm', false)
            ->assertSee('2026-12')
            ->assertSee('DRAFT')
            ->call('openCreate')
            ->set('payPeriodId', (string) $period->id)
            ->call('openRun')
            ->assertHasNoErrors();

        $this->assertSame(1, PayRun::query()->count());
        $this->assertSame(PayRunStatus::DRAFT, PayRun::query()->first()->status);
    }

    public function test_an_hr_manager_can_open_the_detail_page_but_cannot_calculate(): void
    {
        $company = $this->company();
        $run = $this->runFor($company);
        $other = $this->company();
        $otherRun = $this->runFor($other);

        $this->loginAs('hr-manager', $company);
        CompanyContext::set($company->id);

        $this->get(route('payroll.pay-runs.show', $run))->assertOk();
        $this->get(route('payroll.pay-runs.show', $otherRun))->assertNotFound();

        Livewire::test(PayRunShow::class, ['run' => $run])
            ->call('calculate')
            ->assertForbidden();
    }

    public function test_calculate_shows_a_blocked_message_and_leaves_the_run_draft(): void
    {
        $company = $this->company();
        $this->loginAs('payroll-officer', $company);
        CompanyContext::set($company->id);

        $period = PayPeriod::factory()->create([
            'company_id' => $company->id,
            'status' => PeriodStatus::OPEN,
        ]);
        $run = PayRun::factory()->create([
            'company_id' => $company->id,
            'pay_period_id' => $period->id,
            'status' => PayRunStatus::DRAFT,
        ]);

        Livewire::test(PayRunShow::class, ['run' => $run])
            ->call('calculate')
            ->assertSee('The pay run cannot be calculated.')
            ->assertSee("The period's time data is not closed");

        $this->assertSame(PayRunStatus::DRAFT, $run->fresh()->status);
    }

    public function test_approve_send_back_and_lock_follow_the_run_status(): void
    {
        $company = $this->company();
        $officer = $this->loginAs('payroll-officer', $company);
        $approver = User::factory()
            ->forCompanies([$company])
            ->withRole('hr-manager')
            ->create(['must_change_password' => false]);

        CompanyContext::set($company->id);

        $run = PayRun::factory()->create([
            'company_id' => $company->id,
            'pay_period_id' => PayPeriod::factory()->create(['company_id' => $company->id])->id,
            'status' => PayRunStatus::CALCULATED,
            'error_count' => 0,
            'calculated_by' => $officer->id,
            'calculated_at' => now(),
            'time_summary_version' => null,
        ]);

        $this->actingAs($approver);

        Livewire::test(PayRunShow::class, ['run' => $run])
            ->call('approve')
            ->assertSee('APPROVED')
            ->set('sendBackReason', '')
            ->call('sendBack')
            ->assertSee('A reason is required.')
            ->set('sendBackReason', 'Figures need another look')
            ->call('sendBack')
            ->assertSee('CALCULATED')
            ->call('approve')
            ->assertSee('APPROVED');

        $this->assertSame(PayRunStatus::APPROVED, $run->fresh()->status);

        $this->actingAs($officer);
        CompanyContext::set($company->id);

        Livewire::test(PayRunShow::class, ['run' => $run->fresh()])
            ->call('lock')
            ->assertSee('LOCKED');

        $this->assertSame(PayRunStatus::LOCKED, $run->fresh()->status);
    }

    public function test_a_short_override_reason_is_shown_and_not_saved(): void
    {
        $company = $this->company();
        $this->loginAs('payroll-officer', $company);
        CompanyContext::set($company->id);

        $run = PayRun::factory()->create([
            'company_id' => $company->id,
            'pay_period_id' => PayPeriod::factory()->create(['company_id' => $company->id])->id,
            'status' => PayRunStatus::CALCULATED,
        ]);
        $employment = Employment::factory()->create(['company_id' => $company->id]);
        PayRunEmployee::factory()->create([
            'pay_run_id' => $run->id,
            'company_id' => $company->id,
            'employment_id' => $employment->id,
        ]);

        Livewire::test(PayRunShow::class, ['run' => $run])
            ->set('overrideEmploymentId', (string) $employment->id)
            ->set('overrideCode', 'BASIC')
            ->set('overrideAmount', '100.00')
            ->set('overrideReason', 'too short')
            ->call('setOverride')
            ->assertSee('A reason of at least 10 characters is required.');

        $this->assertSame(0, PayRunOverride::query()->count());
    }

    private function runFor(Company $company): PayRun
    {
        return PayRun::factory()->create([
            'company_id' => $company->id,
            'pay_period_id' => PayPeriod::factory()->create(['company_id' => $company->id])->id,
        ]);
    }
}
