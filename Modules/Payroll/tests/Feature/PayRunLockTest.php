<?php

namespace Modules\Payroll\Tests\Feature;

use App\Models\User;
use Database\Seeders\ApprovalDefinitionSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Modules\Calendar\Models\PayPeriod;
use Modules\Calendar\Services\PayPeriodService;
use Modules\CoreHr\Models\Employment;
use Modules\Organisation\Models\Company;
use Modules\Payroll\Database\Seeders\LoanTypeSeeder;
use Modules\Payroll\Enums\AdvanceStatus;
use Modules\Payroll\Enums\ArrearsKind;
use Modules\Payroll\Enums\ExtraDayMethod;
use Modules\Payroll\Enums\FundCode;
use Modules\Payroll\Enums\FundEntryKind;
use Modules\Payroll\Enums\InstalmentStatus;
use Modules\Payroll\Enums\LoanStatus;
use Modules\Payroll\Enums\OtRateMode;
use Modules\Payroll\Enums\PayRunStatus;
use Modules\Payroll\Exceptions\InvalidCompensation;
use Modules\Payroll\Exceptions\InvalidPayRule;
use Modules\Payroll\Exceptions\PayRunBlocked;
use Modules\Payroll\Models\ArrearsEntry;
use Modules\Payroll\Models\EmployeeComponent;
use Modules\Payroll\Models\FundEntry;
use Modules\Payroll\Models\Loan;
use Modules\Payroll\Models\LoanInstalment;
use Modules\Payroll\Models\PayRun;
use Modules\Payroll\Models\PayRunEmployee;
use Modules\Payroll\Models\SalaryAdvance;
use Modules\Payroll\Run\PreRunCheck;
use Modules\Payroll\Services\AdvanceService;
use Modules\Payroll\Services\ArrearsService;
use Modules\Payroll\Services\CompensationService;
use Modules\Payroll\Services\FundService;
use Modules\Payroll\Services\LoanService;
use Modules\Payroll\Services\PayRuleService;
use Modules\Payroll\Services\PayRunService;
use Modules\Workflow\Services\ApprovalService;
use Tests\Support\HrFixtures;
use Tests\TestCase;

class PayRunLockTest extends TestCase
{
    use HrFixtures;
    use RefreshDatabase;

    private Company $company;

    private User $officer;

    private User $approver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(ApprovalDefinitionSeeder::class);
        $this->seed(LoanTypeSeeder::class);
        $this->travelTo('2026-12-15');

        $this->company = $this->company();
        $this->officer = $this->userWith('payroll-officer');
        $this->approver = $this->userWith('hr-manager');
    }

    private function userWith(string $role): User
    {
        return User::factory()
            ->forCompanies([$this->company])
            ->withRole($role)
            ->withPermissions('approval.act')
            ->create(['must_change_password' => false]);
    }

    private function hourly(Employment $employment): void
    {
        $this->actingAs($this->officer);
        Gate::authorize('payroll.config');
        $grade = $employment->assignmentOn('2026-12-31')->grade;
        app(PayRuleService::class)->setExtraDayMethod($grade, ExtraDayMethod::HOURLY, '2026-01-01');
    }

    private function december(): PayPeriod
    {
        return app(PayPeriodService::class)->ensure($this->company, 2026, 12);
    }

    /** Requests, approves and (optionally) pays an advance for December. */
    private function advance(Employment $e, string $amount, bool $pay = true): SalaryAdvance
    {
        $advance = app(AdvanceService::class)->request($e, $this->december(), $amount, 'Urgent need', $this->officer);
        app(ApprovalService::class)->approve($advance->approvalRequest, $this->approver);

        return $pay
            ? app(AdvanceService::class)->markPaid($advance->fresh(), '2026-12-10', null, $this->officer)
            : $advance->fresh();
    }

    /** Requests, approves and pays a loan whose first instalment falls in December. */
    private function loan(Employment $e, string $amount, int $instalments = 1): Loan
    {
        $loan = app(LoanService::class)->request($e, [
            'loan_type_code' => 'STAFF',
            'amount' => $amount,
            'instalments' => $instalments,
            'first_month' => '2026-12-01',
            'reason' => 'Test loan for pay run',
        ], $this->officer);
        app(ApprovalService::class)->approve($loan->approvalRequest, $this->approver);

        return app(LoanService::class)->markPaid($loan->fresh(), '2026-12-01', null, $this->officer);
    }

    /** The carry-forward example: basic 20,000, a PAID advance of 15,000 and a loan instalment of 8,000. */
    private function shortfallEmployee(): Employment
    {
        config(['payroll.advance_limit_percent' => 100]);

        $e = $this->paid('2026-01-01', $this->company, ['basic_salary' => '20000.00']);
        $this->hourly($e);
        $this->advance($e, '15000.00');
        $this->loan($e, '8000.00');

        return $e->fresh();
    }

    private function approved(PayPeriod $period): PayRun
    {
        $runs = app(PayRunService::class);
        $run = $runs->open($period, $this->officer);
        $runs->calculate($run, $this->officer);

        return $runs->approve($run->fresh(), $this->approver);
    }

    private function locked(PayPeriod $period): PayRun
    {
        return app(PayRunService::class)->lock($this->approved($period)->fresh(), $this->officer);
    }

    private function row(PayRun $run, Employment $e): PayRunEmployee
    {
        return PayRunEmployee::query()
            ->where('pay_run_id', $run->id)
            ->where('employment_id', $e->id)
            ->firstOrFail();
    }

    public function test_lock_posts_recoveries_and_the_shortfall(): void
    {
        $e = $this->shortfallEmployee();
        $advance = SalaryAdvance::query()->where('employment_id', $e->id)->firstOrFail();
        $instalment = LoanInstalment::query()->whereHas('loan', fn ($q) => $q->where('employment_id', $e->id))->firstOrFail();

        $period = $this->closedPeriod($this->company);
        $this->summary($e, $period);

        $run = $this->approved($period);

        // Nothing is posted before the lock.
        $this->assertSame(AdvanceStatus::PAID, $advance->fresh()->status);
        $this->assertSame(InstalmentStatus::DUE, $instalment->fresh()->status);
        $this->assertSame('0.00', app(ArrearsService::class)->balance($e->id));
        $this->assertSame(0, ArrearsEntry::query()->count());

        $row = $this->row($run, $e);
        $this->assertSame('0.00', (string) $row->net_pay);
        $this->assertSame('4600.00', (string) $row->carried_forward_out);

        $locked = app(PayRunService::class)->lock($run->fresh(), $this->officer);

        $this->assertSame(PayRunStatus::LOCKED, $locked->status);
        $this->assertSame($this->officer->id, (int) $locked->locked_by);
        $this->assertNotNull($locked->locked_at);
        $this->assertSame(1, $locked->events()->where('event', 'LOCKED')->count());

        $advance = $advance->fresh();
        $this->assertSame(AdvanceStatus::RECOVERED, $advance->status);
        $this->assertSame($run->id, (int) $advance->recovered_pay_run_id);

        $instalment = $instalment->fresh();
        $this->assertSame(InstalmentStatus::RECOVERED, $instalment->status);
        $this->assertSame($run->id, (int) $instalment->pay_run_id);

        $this->assertSame('4600.00', app(ArrearsService::class)->balance($e->id));
        $entries = ArrearsEntry::query()->where('employment_id', $e->id)->get();
        $this->assertCount(1, $entries);
        $this->assertSame(ArrearsKind::SHORTFALL, $entries->first()->kind);
        $this->assertSame($run->id, (int) $entries->first()->pay_run_id);
        $this->assertSame($period->id, (int) $entries->first()->pay_period_id);
    }

    public function test_next_month_recovers_the_carried_forward_balance(): void
    {
        $e = $this->shortfallEmployee();
        $december = $this->closedPeriod($this->company);
        $this->summary($e, $december);
        $this->locked($december);
        $this->assertSame('4600.00', app(ArrearsService::class)->balance($e->id));

        $january = $this->closedPeriod($this->company, '2027-01');
        $this->summary($e, $january);

        $runs = app(PayRunService::class);
        $run = $runs->calculate($runs->open($january, $this->officer), $this->officer);

        $row = $this->row($run, $e);
        $cf = $row->lines()->where('code', 'CF')->firstOrFail();
        $this->assertSame('4600.00', (string) $cf->amount);
        $this->assertSame('4600.00', (string) $row->carried_forward_in);
        $this->assertSame('13800.00', (string) $row->net_pay);
        $this->assertSame('0.00', (string) $row->carried_forward_out);

        // The balance is untouched until the January run is locked.
        $this->assertSame('4600.00', app(ArrearsService::class)->balance($e->id));

        $runs->approve($run->fresh(), $this->approver);
        $runs->lock($run->fresh(), $this->officer);

        $this->assertSame('0.00', app(ArrearsService::class)->balance($e->id));
        $recovery = ArrearsEntry::query()->where('employment_id', $e->id)->where('kind', ArrearsKind::RECOVERY)->firstOrFail();
        $this->assertSame('-4600.00', (string) $recovery->amount);
        $this->assertSame($run->id, (int) $recovery->pay_run_id);
        $this->assertSame(2, ArrearsEntry::query()->where('employment_id', $e->id)->count());
    }

    public function test_fund_deductions_build_fund_balances(): void
    {
        $e = $this->paid('2026-01-01', $this->company, [], ['SAVINGS' => '2000.00']);
        $this->hourly($e);

        $period = $this->closedPeriod($this->company);
        $this->summary($e, $period);

        $run = $this->approved($period);
        $savings = $this->row($run, $e)->lines()->where('code', 'SAVINGS')->firstOrFail();
        $this->assertSame('2000.00', (string) $savings->amount);
        $this->assertSame('0.00', app(FundService::class)->balance($e->id, FundCode::SAVINGS));

        app(PayRunService::class)->lock($run->fresh(), $this->officer);

        $this->assertSame('2000.00', app(FundService::class)->balance($e->id, FundCode::SAVINGS));
        $this->assertSame('0.00', app(FundService::class)->balance($e->id, FundCode::DEPOSIT));
        $entry = FundEntry::query()->where('employment_id', $e->id)->firstOrFail();
        $this->assertSame(FundCode::SAVINGS, $entry->fund);
        $this->assertSame(FundEntryKind::CONTRIBUTION, $entry->kind);
        $this->assertSame($run->id, (int) $entry->pay_run_id);
        $this->assertSame($period->id, (int) $entry->pay_period_id);
        $this->assertSame(1, FundEntry::query()->count());
    }

    public function test_loan_is_settled_when_its_last_instalment_is_recovered(): void
    {
        $e = $this->paid('2026-01-01', $this->company);
        $this->hourly($e);
        $loan = $this->loan($e, '5000.00', 2);

        $december = $this->closedPeriod($this->company);
        $this->summary($e, $december);
        $decemberRun = $this->locked($december);

        // December recovered the first instalment (2,500); one is still due.
        $instalments = LoanInstalment::query()->where('loan_id', $loan->id)->orderBy('sequence')->get();
        $this->assertSame(InstalmentStatus::RECOVERED, $instalments[0]->status);
        $this->assertSame($decemberRun->id, (int) $instalments[0]->pay_run_id);
        $this->assertSame(InstalmentStatus::DUE, $instalments[1]->status);
        $this->assertSame(LoanStatus::ACTIVE, $loan->fresh()->status);

        $january = $this->closedPeriod($this->company, '2027-01');
        $this->summary($e, $january);
        $januaryRun = $this->locked($january);

        $instalments = LoanInstalment::query()->where('loan_id', $loan->id)->orderBy('sequence')->get();
        $this->assertSame(InstalmentStatus::RECOVERED, $instalments[1]->status);
        $this->assertSame($januaryRun->id, (int) $instalments[1]->pay_run_id);
        $this->assertSame(LoanStatus::SETTLED, $loan->fresh()->status);
    }

    public function test_unrecovered_advance_from_an_earlier_period_is_taken_by_this_run(): void
    {
        $e = $this->paid('2026-01-01', $this->company);
        $this->hourly($e);

        // Approved in December but only paid after the December run was locked.
        $advance = $this->advance($e, '10000.00', pay: false);

        $december = $this->closedPeriod($this->company);
        $this->summary($e, $december);
        $decemberRun = $this->locked($december);

        $this->assertSame(AdvanceStatus::APPROVED, $advance->fresh()->status);
        $this->assertNull($advance->fresh()->recovered_pay_run_id);
        $this->assertSame('55200.00', (string) $this->row($decemberRun, $e)->net_pay);

        app(AdvanceService::class)->markPaid($advance->fresh(), '2026-12-20', null, $this->officer);

        $january = $this->closedPeriod($this->company, '2027-01');
        $this->summary($e, $january);

        $runs = app(PayRunService::class);
        $run = $runs->calculate($runs->open($january, $this->officer), $this->officer);

        $line = $this->row($run, $e)->lines()->where('code', 'ADVANCE')->firstOrFail();
        $this->assertSame('10000.00', (string) $line->amount);
        $this->assertSame('salary_advance', $line->source_type);
        $this->assertSame($advance->id, (int) $line->source_id);
        $this->assertSame('45200.00', (string) $this->row($run, $e)->net_pay);

        $runs->approve($run->fresh(), $this->approver);
        $runs->lock($run->fresh(), $this->officer);

        $advance = $advance->fresh();
        $this->assertSame(AdvanceStatus::RECOVERED, $advance->status);
        $this->assertSame($run->id, (int) $advance->recovered_pay_run_id);
    }

    public function test_lock_is_all_or_nothing(): void
    {
        $first = $this->shortfallEmployee();
        $second = $this->shortfallEmployee();

        $period = $this->closedPeriod($this->company);
        $this->summary($first, $period);
        $this->summary($second, $period);

        $run = $this->approved($period);
        $this->assertSame('9200.00', (string) $run->fresh()->carried_forward_out);

        // ArrearsService is final and cannot be mocked, so the failure is injected where recordShortfall writes:
        // the second employee's shortfall entry cannot be saved.
        ArrearsEntry::creating(function (ArrearsEntry $entry) use ($second) {
            if ((int) $entry->employment_id === (int) $second->id) {
                throw new \RuntimeException('The ledger could not be written.');
            }
        });

        try {
            app(PayRunService::class)->lock($run->fresh(), $this->officer);
            $this->fail('Expected the lock to fail.');
        } catch (\RuntimeException $e) {
            $this->assertSame('The ledger could not be written.', $e->getMessage());
        }

        $fresh = $run->fresh();
        $this->assertSame(PayRunStatus::APPROVED, $fresh->status);
        $this->assertNull($fresh->locked_by);
        $this->assertNull($fresh->locked_at);
        $this->assertSame(0, $fresh->events()->where('event', 'LOCKED')->count());

        $this->assertSame(0, SalaryAdvance::query()->where('status', AdvanceStatus::RECOVERED)->count());
        $this->assertSame(0, SalaryAdvance::query()->whereNotNull('recovered_pay_run_id')->count());
        $this->assertSame(2, SalaryAdvance::query()->where('status', AdvanceStatus::PAID)->count());
        $this->assertSame(0, LoanInstalment::query()->where('status', InstalmentStatus::RECOVERED)->count());
        $this->assertSame(0, LoanInstalment::query()->whereNotNull('pay_run_id')->count());
        $this->assertSame(2, Loan::query()->where('status', LoanStatus::ACTIVE)->count());
        $this->assertSame(0, ArrearsEntry::query()->count());
        $this->assertSame(0, FundEntry::query()->count());
        $this->assertSame('0.00', app(ArrearsService::class)->balance($first->id));
        $this->assertSame('0.00', app(ArrearsService::class)->balance($second->id));
    }

    public function test_previous_period_must_be_locked_before_the_next_is_calculated(): void
    {
        $e = $this->paid('2026-01-01', $this->company);
        $this->hourly($e);

        $december = $this->closedPeriod($this->company);
        $this->summary($e, $december);
        $january = $this->closedPeriod($this->company, '2027-01');
        $this->summary($e, $january);

        $runs = app(PayRunService::class);
        $decemberRun = $runs->open($december, $this->officer);
        $runs->calculate($decemberRun, $this->officer);
        $januaryRun = $runs->open($january, $this->officer);

        // December is calculated, then approved, but not locked: January stays blocked.
        foreach ([false, true] as $approveFirst) {
            if ($approveFirst) {
                $runs->approve($decemberRun->fresh(), $this->approver);
            }

            try {
                $runs->calculate($januaryRun->fresh(), $this->officer);
                $this->fail('Expected PayRunBlocked.');
            } catch (PayRunBlocked $blocked) {
                $this->assertSame(['PR02'], array_map(fn (PreRunCheck $c) => $c->code, $blocked->checks));
                $this->assertSame(1, $blocked->checks[0]->count);
            }
            $this->assertSame(PayRunStatus::DRAFT, $januaryRun->fresh()->status);
        }

        $runs->lock($decemberRun->fresh(), $this->officer);

        $calculated = $runs->calculate($januaryRun->fresh(), $this->officer);
        $this->assertSame(PayRunStatus::CALCULATED, $calculated->status);
        $this->assertSame(1, $calculated->employee_count);
    }

    public function test_rules_and_compensation_cannot_change_inside_a_locked_period(): void
    {
        $e = $this->paid('2026-01-01', $this->company, [], ['FUEL' => '5000.00']);
        $this->hourly($e);

        $period = $this->closedPeriod($this->company);
        $this->summary($e, $period);

        // While the run is only approved (not locked) the rules can still be changed.
        $this->approved($period);
        $this->actingAs($this->officer);
        $rules = app(PayRuleService::class);
        $rules->setTier('T1', 'Tier 1', '5000.00', '1050.00', '2026-12-01');

        $locked = app(PayRunService::class)->lock(PayRun::query()->where('pay_period_id', $period->id)->firstOrFail(), $this->officer);
        $this->assertSame(PayRunStatus::LOCKED, $locked->status);

        // Pay rules: nothing may start on or before the last day of the locked period.
        $this->assertThrowsInstanceOf(InvalidPayRule::class, fn () => $rules->setTier('T1', 'Tier 1', '5000.00', '1100.00', '2026-12-01'));
        $this->assertThrowsInstanceOf(InvalidPayRule::class, fn () => $rules->setTier('T2', 'Tier 2', '10000.00', '1150.00', '2026-11-01'));
        $this->assertThrowsInstanceOf(InvalidPayRule::class, fn () => $rules->setStatutoryRate('EPF_EE', '9.00', '2026-12-01'));
        $this->assertThrowsInstanceOf(InvalidPayRule::class, fn () => $rules->setExtraDayMethod($e->assignmentOn('2026-12-31')->grade, ExtraDayMethod::DAILY, '2026-12-01'));
        $this->assertThrowsInstanceOf(InvalidPayRule::class, fn () => $rules->setTaxTable('2026-12-01', [
            ['width' => '200000.00', 'percent' => '0.00'],
            ['width' => null, 'percent' => '10.00'],
        ]));
        $this->assertThrowsInstanceOf(InvalidPayRule::class, fn () => $rules->setCompanySettings($this->company, [
            'late_divisor' => 14400,
            'attendance_min_offset' => '2.0',
            'attendance_max_offset' => '3.0',
            'hours_per_month' => 240,
            'hours_per_day' => 8,
            'ot_multiplier' => '1.50',
        ], '2026-12-01'));

        // Compensation and components: nothing may start or end inside the locked period.
        $compensation = app(CompensationService::class);
        $this->assertThrowsInstanceOf(InvalidCompensation::class, fn () => $compensation->set($e, '2026-12-01', [
            'basic_salary' => '70000.00',
            'ot_rate_mode' => OtRateMode::CALCULATED,
        ]));
        $this->assertThrowsInstanceOf(InvalidCompensation::class, fn () => $compensation->addComponent($e, 'MOBILE', '1000.00', '2026-12-10'));
        $fuel = EmployeeComponent::query()->where('employment_id', $e->id)->firstOrFail();
        $this->assertThrowsInstanceOf(InvalidCompensation::class, fn () => $compensation->endComponent($fuel, '2026-12-20'));
        $this->assertNull($fuel->fresh()->effective_to);

        // The next period is still free to change.
        $rules->setStatutoryRate('EPF_EE', '8.00', '2027-01-01');
        $changed = $compensation->set($e, '2027-01-01', [
            'basic_salary' => '65000.00',
            'ot_rate_mode' => OtRateMode::CALCULATED,
        ]);
        $this->assertSame('65000.00', (string) $changed->basic_salary);
        $this->assertSame('2027-01-01', $changed->effective_from->toDateString());
    }

    /** @param  class-string<\Throwable>  $class */
    private function assertThrowsInstanceOf(string $class, callable $action): void
    {
        try {
            $action();
        } catch (\Throwable $e) {
            $this->assertInstanceOf($class, $e, 'Unexpected exception: '.$e::class.': '.$e->getMessage());

            return;
        }

        $this->fail("Expected {$class} to be thrown.");
    }
}
