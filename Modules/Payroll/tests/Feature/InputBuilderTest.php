<?php

namespace Modules\Payroll\Tests\Feature;

use App\Models\User;
use Database\Seeders\ApprovalDefinitionSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Modules\Attendance\Services\SummaryService;
use Modules\Calendar\Services\PayPeriodService;
use Modules\CoreHr\Services\BankAccountService;
use Modules\Payroll\Calculator\ComponentSpec;
use Modules\Payroll\Calculator\PayCalculator;
use Modules\Payroll\Database\Seeders\LoanTypeSeeder;
use Modules\Payroll\Database\Seeders\PayComponentSeeder;
use Modules\Payroll\Database\Seeders\PayRuleSeeder;
use Modules\Payroll\Enums\ExtraDayMethod;
use Modules\Payroll\Exceptions\PayCalculationError;
use Modules\Payroll\Run\InputBuilder;
use Modules\Payroll\Services\AdvanceService;
use Modules\Payroll\Services\CompensationService;
use Modules\Payroll\Services\LoanService;
use Modules\Payroll\Services\PayComponentService;
use Modules\Payroll\Services\PayEntryService;
use Modules\Payroll\Services\PayRuleService;
use Modules\Payroll\Services\PayRunService;
use Modules\Workflow\Services\ApprovalService;
use Tests\Support\HrFixtures;
use Tests\TestCase;

class InputBuilderTest extends TestCase
{
    use HrFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(ApprovalDefinitionSeeder::class);
        $this->seed(LoanTypeSeeder::class);
        $this->travelTo('2026-12-15');
    }

    private function officer($company): User
    {
        return User::factory()
            ->forCompanies([$company])
            ->withRole('payroll-officer')
            ->withPermissions('approval.act')
            ->create(['must_change_password' => false]);
    }

    private function ensureHourly($employment, User $by): void
    {
        $this->actingAs($by);
        Gate::authorize('payroll.config');
        $grade = $this->categoryFor($employment);
        app(PayRuleService::class)->setExtraDayMethod($grade, ExtraDayMethod::HOURLY, '2026-01-01');
    }

    public function test_builds_the_standard_example_from_stored_data(): void
    {
        $e = $this->paid(comp: ['attendance_tier_code' => 'T1'], components: [
            'PERF' => '10000.00',
            'FUEL' => '5000.00',
        ]);
        $by = $this->officer($e->company);
        $this->ensureHourly($e, $by);
        app(BankAccountService::class)->set($e, $this->account(['effective_from' => '2026-01-01']));

        $p = app(PayPeriodService::class)->ensure($e->company, 2026, 12);
        $approver = User::factory()->forCompanies([$e->company])->withRole('hr-manager')->withPermissions('approval.act')->create(['must_change_password' => false]);

        $adv = app(AdvanceService::class)->request($e, $p, '10000.00', 'Urgent need', $by);
        app(ApprovalService::class)->approve($adv->approvalRequest, $approver);
        app(AdvanceService::class)->markPaid($adv->fresh(), '2026-12-10', null, $by);

        $loan = app(LoanService::class)->request($e, [
            'loan_type_code' => 'STAFF',
            'amount' => '5000.00',
            'instalments' => 1,
            'first_month' => '2026-12-01',
            'reason' => 'Test loan for pay run',
        ], $by);
        app(ApprovalService::class)->approve($loan->approvalRequest, $approver);
        app(LoanService::class)->markPaid($loan->fresh(), '2026-12-01', null, $by);

        $p = $this->closedPeriod($e->company);
        $this->summary($e, $p, [
            'credited_days' => 24,
            'late_minutes_chargeable' => 30,
            'ot_normal_minutes' => 600,
        ]);

        app(PayEntryService::class)->add($e, $p, [
            'component_code' => 'MEALS',
            'amount' => '3000.00',
        ], $by);

        $run = app(PayRunService::class)->open($p, $by);
        $summary = app(SummaryService::class)->final($p)->firstWhere('employment_id', $e->id);
        $rules = app(PayRuleService::class)->rulesFor($e->company, $p->end_date);
        $components = [];
        foreach (app(PayComponentService::class)->catalogue() as $c) {
            $components[$c->code] = new ComponentSpec(
                $c->code, $c->kind, $c->method, (int) $c->sheet, (bool) $c->epf_liable, (bool) $c->taxable, (int) $c->display_order, $c->night_base_hours !== null ? (int) $c->night_base_hours : null
            );
        }

        $built = app(InputBuilder::class)->build($summary, $run, $rules, $components);
        $input = $built['input'];

        $this->assertSame('60000.00', $input->basicSalary);
        $this->assertSame('25.00', $input->expectedDays);
        $this->assertSame('24.00', $input->creditedDays);
        $this->assertSame(30, $input->lateMinutesChargeable);
        $this->assertSame(600, $input->otMinutes);
        $this->assertSame('375.00', $input->otRate);
        $this->assertSame(ExtraDayMethod::HOURLY, $input->extraDayMethod);
        $this->assertNotNull($input->tier);
        $this->assertCount(1, $input->advances);
        $this->assertCount(1, $input->loanInstalments);

        $result = app(PayCalculator::class)->calculate($input);
        $this->assertSame('57217.00', $result->netPay);
    }

    public function test_quantity_entries_are_summed_into_one_item_with_the_employees_rate(): void
    {
        $e = $this->paid(components: ['VIP_JOBS' => '500.00']);
        $by = $this->officer($e->company);
        $this->ensureHourly($e, $by);
        $p = $this->closedPeriod($e->company);
        $this->summary($e, $p);

        app(PayEntryService::class)->add($e, $p, ['component_code' => 'VIP_JOBS', 'quantity' => '4'], $by);
        app(PayEntryService::class)->add($e, $p, ['component_code' => 'VIP_JOBS', 'quantity' => '2'], $by);

        $run = app(PayRunService::class)->open($p, $by);
        $summary = app(SummaryService::class)->final($p)->firstWhere('employment_id', $e->id);
        $rules = app(PayRuleService::class)->rulesFor($e->company, $p->end_date);
        $components = $this->specs();
        $input = app(InputBuilder::class)->build($summary, $run, $rules, $components)['input'];
        $vip = collect($input->items)->firstWhere('code', 'VIP_JOBS');
        $this->assertSame('6.00', $vip->quantity);
        $this->assertSame('500.00', $vip->amount);
    }

    public function test_component_not_in_force_on_the_last_day_is_left_out(): void
    {
        $e = $this->paid();
        $by = $this->officer($e->company);
        $this->ensureHourly($e, $by);
        app(CompensationService::class)->addComponent($e, 'FUEL', '5000.00', '2026-01-01', '2026-11-30');
        $p = $this->closedPeriod($e->company);
        $this->summary($e, $p);
        $run = app(PayRunService::class)->open($p, $by);
        $summary = app(SummaryService::class)->final($p)->firstWhere('employment_id', $e->id);
        $input = app(InputBuilder::class)->build($summary, $run, app(PayRuleService::class)->rulesFor($e->company, $p->end_date), $this->specs())['input'];
        $this->assertNull(collect($input->items)->firstWhere('code', 'FUEL'));
    }

    public function test_leaver_uses_the_exit_date_for_lookups(): void
    {
        $e = $this->paid();
        $by = $this->officer($e->company);
        $this->ensureHourly($e, $by);
        app(CompensationService::class)->addComponent($e, 'FUEL', '5000.00', '2026-01-01', '2026-12-15');
        $e->update(['exit_date' => '2026-12-15']);
        $p = $this->closedPeriod($e->company);
        $this->summary($e, $p);
        $run = app(PayRunService::class)->open($p, $by);
        $summary = app(SummaryService::class)->final($p)->firstWhere('employment_id', $e->id);
        $input = app(InputBuilder::class)->build($summary, $run, app(PayRuleService::class)->rulesFor($e->company, $p->end_date), $this->specs())['input'];
        $this->assertNotNull(collect($input->items)->firstWhere('code', 'FUEL'));
    }

    public function test_missing_compensation_is_a_readable_error(): void
    {
        $e = $this->hired();
        $by = $this->officer($e->company);
        $this->seed(PayComponentSeeder::class);
        $this->seed(PayRuleSeeder::class);
        $this->ensureHourly($e, $by);
        $p = $this->closedPeriod($e->company);
        $this->summary($e, $p);
        $run = app(PayRunService::class)->open($p, $by);
        $summary = app(SummaryService::class)->final($p)->firstWhere('employment_id', $e->id);

        $this->expectException(PayCalculationError::class);
        $this->expectExceptionMessage('No compensation on 2026-12-31.');
        app(InputBuilder::class)->build($summary, $run, app(PayRuleService::class)->rulesFor($e->company, $p->end_date), $this->specs());
    }

    public function test_snapshot_holds_the_bank_account_and_names_as_at_the_run(): void
    {
        $e = $this->paid();
        $by = $this->officer($e->company);
        $this->ensureHourly($e, $by);
        app(BankAccountService::class)->set($e, $this->account([
            'effective_from' => '2026-01-01',
            'account_no' => '99887766',
            'account_name' => 'N Perera',
        ]));
        $p = $this->closedPeriod($e->company);
        $this->summary($e, $p);
        $run = app(PayRunService::class)->open($p, $by);
        $summary = app(SummaryService::class)->final($p)->firstWhere('employment_id', $e->id);
        $snap = app(InputBuilder::class)->build($summary, $run, app(PayRuleService::class)->rulesFor($e->company, $p->end_date), $this->specs())['snapshot'];
        $this->assertSame('99887766', $snap['account_no']);
        $this->assertSame('N Perera', $snap['account_name']);
        $this->assertSame($e->employee_no, $snap['employee_no']);
        $this->assertSame($e->person->name_with_initials, $snap['name']);
    }

    private function specs(): array
    {
        $out = [];
        foreach (app(PayComponentService::class)->catalogue() as $c) {
            $out[$c->code] = new ComponentSpec(
                $c->code, $c->kind, $c->method, (int) $c->sheet, (bool) $c->epf_liable, (bool) $c->taxable, (int) $c->display_order, $c->night_base_hours !== null ? (int) $c->night_base_hours : null
            );
        }

        return $out;
    }
}
