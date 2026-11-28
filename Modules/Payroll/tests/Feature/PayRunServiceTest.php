<?php

namespace Modules\Payroll\Tests\Feature;

use App\Models\User;
use Database\Seeders\ApprovalDefinitionSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Modules\Attendance\Models\PeriodTimeSummary;
use Modules\Attendance\Services\PeriodCloseService;
use Modules\Attendance\Services\SummaryService;
use Modules\Calendar\Exceptions\InvalidPeriodAction;
use Modules\Calendar\Models\PayPeriod;
use Modules\Calendar\Services\PayPeriodService;
use Modules\CoreHr\Models\Employment;
use Modules\CoreHr\Services\BankAccountService;
use Modules\Organisation\Models\Company;
use Modules\Payroll\Calculator\ComponentSpec;
use Modules\Payroll\Calculator\PayCalculator;
use Modules\Payroll\Calculator\PayResult;
use Modules\Payroll\Database\Seeders\LoanTypeSeeder;
use Modules\Payroll\Enums\ExtraDayMethod;
use Modules\Payroll\Enums\PayRunEmployeeStatus;
use Modules\Payroll\Enums\PayRunStatus;
use Modules\Payroll\Exceptions\InvalidAdvance;
use Modules\Payroll\Exceptions\InvalidPayEntry;
use Modules\Payroll\Exceptions\InvalidPayRunAction;
use Modules\Payroll\Models\PayRun;
use Modules\Payroll\Models\PayRunEmployee;
use Modules\Payroll\Models\PayRunLine;
use Modules\Payroll\Run\InputBuilder;
use Modules\Payroll\Services\AdvanceService;
use Modules\Payroll\Services\LoanService;
use Modules\Payroll\Services\PayComponentService;
use Modules\Payroll\Services\PayEntryService;
use Modules\Payroll\Services\PayRuleService;
use Modules\Payroll\Services\PayRunOverrideService;
use Modules\Payroll\Services\PayRunService;
use Modules\Workflow\Services\ApprovalService;
use Tests\Support\HrFixtures;
use Tests\TestCase;

class PayRunServiceTest extends TestCase
{
    use HrFixtures;
    use RefreshDatabase;

    private const STANDARD_LINES = [
        'BASIC', 'OT', 'ATT_ALLOW', 'PERF', 'FUEL', 'LATE', 'EPF_EE', 'ADVANCE', 'LOAN', 'MEALS', 'EPF_ER', 'ETF_ER',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(ApprovalDefinitionSeeder::class);
        $this->seed(LoanTypeSeeder::class);
        $this->travelTo('2026-12-15');
    }

    private function officer(Company $company): User
    {
        return User::factory()
            ->forCompanies([$company])
            ->withRole('payroll-officer')
            ->withPermissions('approval.act')
            ->create(['must_change_password' => false]);
    }

    private function approver(Company $company): User
    {
        return User::factory()
            ->forCompanies([$company])
            ->withRole('hr-manager')
            ->withPermissions('approval.act')
            ->create(['must_change_password' => false]);
    }

    /** Holds payrun.manage and payrun.approve at once, so one user can both calculate and (try to) approve. */
    private function superUser(Company $company): User
    {
        return User::factory()
            ->forCompanies([$company])
            ->withRole('super-admin')
            ->create(['must_change_password' => false]);
    }

    private function hourly(Employment $employment, User $by): void
    {
        $this->actingAs($by);
        Gate::authorize('payroll.config');
        $grade = $this->categoryFor($employment);
        app(PayRuleService::class)->setExtraDayMethod($grade, ExtraDayMethod::HOURLY, '2026-01-01');
    }

    private function december(Company $company): PayPeriod
    {
        return app(PayPeriodService::class)->ensure($company, 2026, 12);
    }

    /** The "standard example" employee: everything is stored before the period is closed. */
    private function standard(Company $company, User $officer, User $approver): Employment
    {
        $e = $this->paid('2026-01-01', $company, ['attendance_tier_code' => 'T1'], [
            'PERF' => '10000.00',
            'FUEL' => '5000.00',
        ]);
        $this->hourly($e, $officer);
        app(BankAccountService::class)->set($e, $this->account(['effective_from' => '2026-01-01']));

        $period = $this->december($company);
        app(PayEntryService::class)->add($e, $period, ['component_code' => 'MEALS', 'amount' => '3000.00'], $officer);

        $advance = app(AdvanceService::class)->request($e, $period, '10000.00', 'Urgent need', $officer);
        app(ApprovalService::class)->approve($advance->approvalRequest, $approver);
        app(AdvanceService::class)->markPaid($advance->fresh(), '2026-12-10', null, $officer);

        $loan = app(LoanService::class)->request($e, [
            'loan_type_code' => 'STAFF',
            'amount' => '5000.00',
            'instalments' => 1,
            'first_month' => '2026-12-01',
            'reason' => 'Test loan for pay run',
        ], $officer);
        app(ApprovalService::class)->approve($loan->approvalRequest, $approver);
        app(LoanService::class)->markPaid($loan->fresh(), '2026-12-01', null, $officer);

        return $e->fresh();
    }

    private function standardSummary(Employment $e, PayPeriod $p): PeriodTimeSummary
    {
        return $this->summary($e, $p, [
            'credited_days' => 24,
            'late_minutes_chargeable' => 30,
            'ot_normal_minutes' => 600,
        ]);
    }

    /** A full-month employee on 60,000 basic whose net pay is 55,200. */
    private function fullMonth(Company $company, User $officer): Employment
    {
        $e = $this->paid('2026-01-01', $company);
        $this->hourly($e, $officer);

        return $e;
    }

    /** @return array<string, ComponentSpec> */
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

    private function calculatorResult(Employment $e, PayPeriod $p, PayRun $run): PayResult
    {
        $summary = app(SummaryService::class)->final($p)->firstWhere('employment_id', $e->id);
        $rules = app(PayRuleService::class)->rulesFor($e->company, $p->end_date);
        $built = app(InputBuilder::class)->build($summary, $run, $rules, $this->specs());

        return app(PayCalculator::class)->calculate($built['input']);
    }

    private function row(PayRun $run, Employment $e): PayRunEmployee
    {
        return PayRunEmployee::query()
            ->where('pay_run_id', $run->id)
            ->where('employment_id', $e->id)
            ->firstOrFail();
    }

    /**
     * An employee who passes every pre-run check but still cannot be calculated: they left in November,
     * before the extra-day method of their grade came into force, and they have an extra day in the summary.
     *
     * @return array{0: Company, 1: User, 2: Employment, 3: Employment, 4: PayPeriod}
     */
    private function runWithOneEmployeeInError(): array
    {
        $company = $this->company();
        $officer = $this->officer($company);

        $ok = $this->fullMonth($company, $officer);

        $bad = $this->paid('2026-01-01', $company);
        $this->actingAs($officer);
        Gate::authorize('payroll.config');
        app(PayRuleService::class)->setExtraDayMethod($this->categoryFor($bad), ExtraDayMethod::HOURLY, '2026-12-01');
        $bad->update(['exit_date' => '2026-11-20']);

        $period = $this->closedPeriod($company);
        $this->summary($ok, $period);
        $this->summary($bad->fresh(), $period, ['extra_days' => 1]);

        return [$company, $officer, $ok, $bad->fresh(), $period];
    }

    /** @param  class-string<\Throwable>  $class */
    private function assertRefused(string $class, callable $action, ?string $message = null): void
    {
        try {
            $action();
        } catch (\Throwable $e) {
            $this->assertInstanceOf($class, $e, 'Unexpected exception: '.$e::class.': '.$e->getMessage());
            if ($message !== null) {
                $this->assertSame($message, $e->getMessage());
            }

            return;
        }

        $this->fail("Expected {$class} to be thrown.");
    }

    public function test_open_creates_one_draft_run_per_period(): void
    {
        $company = $this->company();
        $officer = $this->officer($company);
        $period = $this->december($company);
        $runs = app(PayRunService::class);

        $this->assertNull($runs->forPeriod($period));

        $first = $runs->open($period, $officer);
        $second = $runs->open($period, $officer);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(PayRunStatus::DRAFT, $first->status);
        $this->assertSame($company->id, (int) $first->company_id);
        $this->assertSame($period->id, (int) $first->pay_period_id);
        $this->assertSame(1, PayRun::query()->count());
        $this->assertSame($first->id, $runs->forPeriod($period)->id);
    }

    public function test_calculate_stores_snapshot_lines_and_totals(): void
    {
        $company = $this->company();
        $officer = $this->officer($company);
        $approver = $this->approver($company);

        $full = $this->fullMonth($company, $officer);
        $standard = $this->standard($company, $officer, $approver);

        $period = $this->closedPeriod($company);
        $this->summary($full, $period);
        $this->standardSummary($standard, $period);

        $runs = app(PayRunService::class);
        $run = $runs->calculate($runs->open($period, $officer), $officer);
        $run = $run->fresh();

        $this->assertSame(PayRunStatus::CALCULATED, $run->status);
        $this->assertSame(2, $run->employee_count);
        $this->assertSame(0, $run->error_count);
        $this->assertSame(1, $run->time_summary_version);
        $this->assertSame('112417.00', (string) $run->net_pay);
        $this->assertSame($officer->id, (int) $run->calculated_by);
        $this->assertNotNull($run->calculated_at);

        $events = $run->events()->get();
        $this->assertCount(1, $events);
        $this->assertSame('CALCULATED', $events->first()->event);
        $this->assertSame($officer->id, (int) $events->first()->user_id);

        // The full-month employee.
        $fullRow = $this->row($run, $full);
        $this->assertSame(PayRunEmployeeStatus::OK, $fullRow->status);
        $this->assertSame('55200.00', (string) $fullRow->net_pay);

        // The standard employee: snapshot, totals and lines.
        $row = $this->row($run, $standard);
        $this->assertSame(PayRunEmployeeStatus::OK, $row->status);
        $this->assertNull($row->error);
        $this->assertSame($standard->employee_no, $row->employee_no);
        $this->assertSame($standard->person->name_with_initials, $row->name);
        $this->assertSame('60000.00', (string) $row->basic_salary);
        $this->assertSame('375.00', (string) $row->ot_rate);
        $this->assertSame(ExtraDayMethod::HOURLY, $row->extra_day_method);
        $this->assertSame('T1', $row->attendance_tier_code);
        $this->assertSame('24.00', (string) $row->credited_days);
        $this->assertSame(30, $row->late_minutes_chargeable);
        $this->assertSame(600, $row->ot_minutes);
        $this->assertSame('0.00', (string) $row->carried_forward_in);
        $this->assertSame('0012345678', $row->account_no);
        $this->assertSame('N Perera', $row->account_name);
        $this->assertSame('79950.00', bcadd((string) $row->gross_sheet1, (string) $row->gross_sheet2, 2));
        $this->assertSame('22733.00', (string) $row->total_deductions);
        $this->assertSame('57217.00', (string) $row->net_pay);
        $this->assertSame('0.00', (string) $row->carried_forward_out);

        $stored = $row->lines()->orderBy('id')->get();
        $this->assertSame(self::STANDARD_LINES, $stored->pluck('code')->all());

        $expected = $this->calculatorResult($standard, $period, $run);
        $this->assertCount(count($expected->lines), $stored);
        foreach ($expected->lines as $i => $line) {
            $this->assertSame($line->code, $stored[$i]->code, "Line {$i} code");
            $this->assertSame($line->kind, $stored[$i]->kind, "Line {$line->code} kind");
            $this->assertSame((string) $line->amount, (string) $stored[$i]->amount, "Line {$line->code} amount");
            $this->assertSame($line->sheet, $stored[$i]->sheet, "Line {$line->code} sheet");
            $this->assertFalse($stored[$i]->is_override);
        }
        $this->assertSame('57600.00', (string) $stored->firstWhere('code', 'BASIC')->amount);
        $this->assertSame('salary_advance', $stored->firstWhere('code', 'ADVANCE')->source_type);
        $this->assertSame('loan_instalment', $stored->firstWhere('code', 'LOAN')->source_type);
    }

    public function test_recalculation_replaces_rows_and_gives_the_same_figures(): void
    {
        $company = $this->company();
        $officer = $this->officer($company);
        $approver = $this->approver($company);

        $full = $this->fullMonth($company, $officer);
        $standard = $this->standard($company, $officer, $approver);

        $period = $this->closedPeriod($company);
        $this->summary($full, $period);
        $this->standardSummary($standard, $period);

        $runs = app(PayRunService::class);
        $run = $runs->calculate($runs->open($period, $officer), $officer);

        $snapshot = fn (PayRun $r) => PayRunEmployee::query()
            ->where('pay_run_id', $r->id)
            ->orderBy('employment_id')
            ->get()
            ->map(fn (PayRunEmployee $row) => [
                'employment_id' => $row->employment_id,
                'net_pay' => (string) $row->net_pay,
                'total_deductions' => (string) $row->total_deductions,
                'lines' => $row->lines()->orderBy('id')->get()->map(fn (PayRunLine $l) => [$l->code, (string) $l->amount, $l->sheet])->all(),
            ])
            ->all();

        $before = $snapshot($run);
        $oldIds = PayRunEmployee::query()->where('pay_run_id', $run->id)->pluck('id')->all();
        $oldTotals = [(string) $run->fresh()->net_pay, (string) $run->fresh()->total_deductions];

        $again = $runs->calculate($run->fresh(), $officer)->fresh();

        $this->assertSame($before, $snapshot($again));
        $this->assertSame(2, PayRunEmployee::query()->where('pay_run_id', $run->id)->count());
        $this->assertSame([], array_values(array_intersect($oldIds, PayRunEmployee::query()->where('pay_run_id', $run->id)->pluck('id')->all())));
        $this->assertSame($oldTotals, [(string) $again->net_pay, (string) $again->total_deductions]);
        $this->assertSame(PayRunStatus::CALCULATED, $again->status);
        $this->assertSame(2, $again->employee_count);
        $this->assertSame(2, $again->events()->where('event', 'CALCULATED')->count());
    }

    public function test_one_employee_in_error_does_not_stop_the_others(): void
    {
        [, $officer, $ok, $bad, $period] = $this->runWithOneEmployeeInError();

        $runs = app(PayRunService::class);
        $run = $runs->calculate($runs->open($period, $officer), $officer)->fresh();

        $this->assertSame(PayRunStatus::CALCULATED, $run->status);
        $this->assertSame(2, $run->employee_count);
        $this->assertSame(1, $run->error_count);
        $this->assertSame('55200.00', (string) $run->net_pay);

        $badRow = $this->row($run, $bad);
        $this->assertSame(PayRunEmployeeStatus::ERROR, $badRow->status);
        $this->assertNotEmpty($badRow->error);
        $this->assertSame('0.00', (string) $badRow->net_pay);
        $this->assertSame('0.00', (string) $badRow->total_deductions);
        $this->assertSame(0, $badRow->lines()->count());

        $okRow = $this->row($run, $ok);
        $this->assertSame(PayRunEmployeeStatus::OK, $okRow->status);
        $this->assertNull($okRow->error);
        $this->assertSame('55200.00', (string) $okRow->net_pay);
        $this->assertGreaterThan(0, $okRow->lines()->count());
    }

    public function test_approval_needs_a_different_user_no_errors_and_current_time_data(): void
    {
        [$company, $officer, $ok, $bad, $period] = $this->runWithOneEmployeeInError();
        $boss = $this->superUser($company);
        $approver = $this->approver($company);
        $runs = app(PayRunService::class);

        $run = $runs->open($period, $boss);
        $runs->calculate($run, $boss);
        $this->assertSame(1, $run->fresh()->error_count);

        // 1. An employee in error blocks approval.
        $this->assertRefused(InvalidPayRunAction::class, fn () => $runs->approve($run->fresh(), $approver));
        $this->assertSame(PayRunStatus::CALCULATED, $run->fresh()->status);

        // Fix the data and recalculate.
        PeriodTimeSummary::query()->where('employment_id', $bad->id)->update(['extra_days' => 0]);
        $runs->calculate($run->fresh(), $boss);
        $this->assertSame(0, $run->fresh()->error_count);
        $this->assertSame('110400.00', (string) $run->fresh()->net_pay);

        // 2. The calculator may not approve their own run, even with the permission.
        $this->assertRefused(InvalidPayRunAction::class, fn () => $runs->approve($run->fresh(), $boss));
        $this->assertSame(PayRunStatus::CALCULATED, $run->fresh()->status);

        // 3. A newer version of the time data makes the run stale.
        $this->summary($ok, $period, ['version' => 2]);
        $this->summary($bad, $period, ['version' => 2]);
        $this->assertRefused(
            InvalidPayRunAction::class,
            fn () => $runs->approve($run->fresh(), $approver),
            'The time data changed. Recalculate.',
        );

        $runs->calculate($run->fresh(), $officer);
        $this->assertSame(2, $run->fresh()->time_summary_version);

        // 4. A different user, no errors, current data.
        $approved = $runs->approve($run->fresh(), $approver);

        $this->assertSame(PayRunStatus::APPROVED, $approved->status);
        $this->assertSame($approver->id, (int) $approved->approved_by);
        $this->assertNotNull($approved->approved_at);
        $this->assertSame(1, $approved->events()->where('event', 'APPROVED')->count());
    }

    public function test_send_back_returns_an_approved_run_to_calculated(): void
    {
        $company = $this->company();
        $officer = $this->officer($company);
        $approver = $this->approver($company);
        $employment = $this->fullMonth($company, $officer);
        $period = $this->closedPeriod($company);
        $this->summary($employment, $period);

        $runs = app(PayRunService::class);
        $run = $runs->open($period, $officer);
        $runs->calculate($run, $officer);

        // Only an approved run can be sent back.
        $this->assertRefused(InvalidPayRunAction::class, fn () => $runs->sendBack($run->fresh(), $approver, 'Not approved yet'));

        $runs->approve($run->fresh(), $approver);

        // A reason is required.
        $this->assertRefused(InvalidPayRunAction::class, fn () => $runs->sendBack($run->fresh(), $approver, '  '));
        $this->assertSame(PayRunStatus::APPROVED, $run->fresh()->status);

        $sent = $runs->sendBack($run->fresh(), $approver, 'Wrong figures for one employee');

        $this->assertSame(PayRunStatus::CALCULATED, $sent->status);
        $this->assertNull($sent->approved_by);
        $this->assertNull($sent->approved_at);
        $event = $sent->events()->where('event', 'SENT_BACK')->first();
        $this->assertNotNull($event);
        $this->assertSame('Wrong figures for one employee', $event->note);
        $this->assertSame($approver->id, (int) $event->user_id);

        // The payroll officer (payrun.manage) may send a run back too.
        $runs->approve($run->fresh(), $approver);
        $again = $runs->sendBack($run->fresh(), $officer, 'Officer correction');
        $this->assertSame(PayRunStatus::CALCULATED, $again->status);
        $this->assertSame(2, $again->events()->where('event', 'SENT_BACK')->count());
    }

    public function test_locked_run_refuses_every_change(): void
    {
        $company = $this->company();
        $officer = $this->officer($company);
        $approver = $this->approver($company);
        $employment = $this->fullMonth($company, $officer);
        $period = $this->closedPeriod($company);
        $this->summary($employment, $period);

        $runs = app(PayRunService::class);
        $run = $runs->open($period, $officer);
        $runs->calculate($run, $officer);
        $runs->approve($run->fresh(), $approver);
        $locked = $runs->lock($run->fresh(), $officer);
        $this->assertSame(PayRunStatus::LOCKED, $locked->status);

        $this->assertRefused(InvalidPayRunAction::class, fn () => $runs->calculate($locked->fresh(), $officer));
        $this->assertRefused(InvalidPayRunAction::class, fn () => $runs->approve($locked->fresh(), $approver));
        $this->assertRefused(InvalidPayRunAction::class, fn () => $runs->sendBack($locked->fresh(), $approver, 'Trying to reopen'));
        $this->assertRefused(InvalidPayRunAction::class, fn () => $runs->lock($locked->fresh(), $officer));
        $this->assertRefused(InvalidPayRunAction::class, fn () => $runs->recalculateEmployee($locked->fresh(), $employment->id, $officer));
        $this->assertRefused(
            InvalidPayRunAction::class,
            fn () => app(PayRunOverrideService::class)->set($locked->fresh(), $employment, 'FUEL', '1000.00', 'Trying to change a locked run', $officer),
        );

        // The models refuse save() and delete() once the run is locked.
        $row = $this->row($locked, $employment);
        $line = $row->lines()->firstOrFail();

        $this->assertRefused(\LogicException::class, fn () => PayRun::query()->findOrFail($locked->id)->update(['net_pay' => '1.00']));
        $this->assertRefused(\LogicException::class, fn () => PayRun::query()->findOrFail($locked->id)->delete());
        $this->assertRefused(\LogicException::class, fn () => $row->update(['net_pay' => '1.00']));
        $this->assertRefused(\LogicException::class, fn () => $row->delete());
        $this->assertRefused(\LogicException::class, fn () => $line->update(['amount' => '1.00']));
        $this->assertRefused(\LogicException::class, fn () => $line->delete());

        $fresh = PayRun::query()->findOrFail($locked->id);
        $this->assertSame(PayRunStatus::LOCKED, $fresh->status);
        $this->assertSame('55200.00', (string) $fresh->net_pay);
        $this->assertSame('55200.00', (string) $this->row($locked, $employment)->net_pay);
    }

    public function test_period_is_frozen_from_approval(): void
    {
        $company = $this->company();
        $officer = $this->officer($company);
        $approver = $this->approver($company);
        $admin = $this->superUser($company);
        $employment = $this->fullMonth($company, $officer);
        $period = $this->closedPeriod($company);
        $this->summary($employment, $period);

        $runs = app(PayRunService::class);
        $run = $runs->open($period, $officer);
        $runs->calculate($run, $officer);

        $this->assertFalse($runs->isPeriodFrozen($period->id));
        $this->assertFalse($runs->isPeriodLocked($period->id));

        $runs->approve($run->fresh(), $approver);

        $this->assertTrue($runs->isPeriodFrozen($period->id));
        $this->assertFalse($runs->isPeriodLocked($period->id));

        $this->assertRefused(
            InvalidPayEntry::class,
            fn () => app(PayEntryService::class)->add($employment, $period, ['component_code' => 'MEALS', 'amount' => '500.00'], $officer),
        );
        $this->assertRefused(
            InvalidAdvance::class,
            fn () => app(AdvanceService::class)->request($employment, $period, '5000.00', 'Too late', $officer),
        );
        $this->assertRefused(
            InvalidPeriodAction::class,
            fn () => app(PeriodCloseService::class)->reopen($period, $admin, 'Need to change time data'),
            'Send the pay run back first.',
        );

        // Sending the run back thaws the period.
        $runs->sendBack($run->fresh(), $approver, 'Need a correction');
        $this->assertFalse($runs->isPeriodFrozen($period->id));
        $entry = app(PayEntryService::class)->add($employment, $period, ['component_code' => 'MEALS', 'amount' => '500.00'], $officer);
        $this->assertNotNull($entry->id);

        // A locked run keeps the period frozen for good.
        $runs->calculate($run->fresh(), $officer);
        $runs->approve($run->fresh(), $approver);
        $runs->lock($run->fresh(), $officer);
        $this->assertTrue($runs->isPeriodFrozen($period->id));
        $this->assertTrue($runs->isPeriodLocked($period->id));
    }

    public function test_actions_need_their_permissions(): void
    {
        $company = $this->company();
        $officer = $this->officer($company);
        $approver = $this->approver($company);
        $employment = $this->fullMonth($company, $officer);
        $period = $this->closedPeriod($company);
        $this->summary($employment, $period);
        $runs = app(PayRunService::class);

        // hr-manager has no payrun.manage.
        $this->assertRefused(AuthorizationException::class, fn () => $runs->open($period, $approver));
        $this->assertNull($runs->forPeriod($period));

        $run = $runs->open($period, $officer);
        $this->assertRefused(AuthorizationException::class, fn () => $runs->calculate($run, $approver));
        $this->assertSame(PayRunStatus::DRAFT, $run->fresh()->status);

        $runs->calculate($run, $officer);

        // payroll-officer has no payrun.approve.
        $this->assertRefused(AuthorizationException::class, fn () => $runs->approve($run->fresh(), $officer));
        $this->assertSame(PayRunStatus::CALCULATED, $run->fresh()->status);

        $runs->approve($run->fresh(), $approver);

        // hr-manager has no payrun.lock; a user with no role may not send back either.
        $this->assertRefused(AuthorizationException::class, fn () => $runs->lock($run->fresh(), $approver));
        $nobody = User::factory()->forCompanies([$company])->create(['must_change_password' => false]);
        $this->assertRefused(AuthorizationException::class, fn () => $runs->sendBack($run->fresh(), $nobody, 'No permission at all'));
        $this->assertRefused(
            AuthorizationException::class,
            fn () => app(PayRunOverrideService::class)->set($run->fresh(), $employment, 'FUEL', '1000.00', 'Approver may not override', $approver),
        );
        $this->assertSame(PayRunStatus::APPROVED, $run->fresh()->status);

        $locked = $runs->lock($run->fresh(), $officer);
        $this->assertSame(PayRunStatus::LOCKED, $locked->status);
    }

    public function test_runs_are_limited_to_the_users_companies(): void
    {
        $companyA = $this->company();
        $companyB = $this->company();
        $officerA = $this->officer($companyA);
        $officerB = $this->officer($companyB);

        $employment = $this->fullMonth($companyA, $officerA);
        $period = $this->closedPeriod($companyA);
        $this->summary($employment, $period);

        $runs = app(PayRunService::class);
        $run = $runs->open($period, $officerA);

        // Company B's officer cannot act on company A's period or run.
        $this->assertRefused(AuthorizationException::class, fn () => $runs->open($period, $officerB));
        $this->assertRefused(AuthorizationException::class, fn () => $runs->calculate($run, $officerB));
        $this->assertSame(PayRunStatus::DRAFT, $run->fresh()->status);

        // ...and cannot see it either.
        $this->actingAs($officerB);
        $this->assertSame(0, PayRun::query()->count());
        $this->assertNull($runs->forPeriod($period));

        $this->actingAs($officerA);
        $this->assertSame(1, PayRun::query()->count());
        $this->assertSame($run->id, $runs->forPeriod($period)->id);

        // An all-companies user sees it.
        $everything = User::factory()->allCompanies()->withRole('payroll-officer')->create(['must_change_password' => false]);
        $this->actingAs($everything);
        $this->assertSame(1, PayRun::query()->count());
        $this->assertSame(PayRunStatus::CALCULATED, $runs->calculate($run->fresh(), $everything)->status);
    }
}
