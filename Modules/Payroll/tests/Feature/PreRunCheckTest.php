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
use Modules\CoreHr\Services\BankAccountService;
use Modules\Organisation\Models\Company;
use Modules\Payroll\Database\Seeders\LoanTypeSeeder;
use Modules\Payroll\Database\Seeders\PayRuleSeeder;
use Modules\Payroll\Enums\ExtraDayMethod;
use Modules\Payroll\Enums\OtRateMode;
use Modules\Payroll\Enums\PayRunStatus;
use Modules\Payroll\Exceptions\PayRunBlocked;
use Modules\Payroll\Models\Loan;
use Modules\Payroll\Models\PayRun;
use Modules\Payroll\Models\PayRunEmployee;
use Modules\Payroll\Models\SalaryAdvance;
use Modules\Payroll\Run\PreRunCheck;
use Modules\Payroll\Run\PreRunChecker;
use Modules\Payroll\Services\AdvanceService;
use Modules\Payroll\Services\CompensationService;
use Modules\Payroll\Services\LoanService;
use Modules\Payroll\Services\PayRuleService;
use Modules\Payroll\Services\PayRunService;
use Modules\Workflow\Services\ApprovalService;
use Tests\Support\HrFixtures;
use Tests\TestCase;

class PreRunCheckTest extends TestCase
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

    private function check(PayPeriod $period, string $code): PreRunCheck
    {
        $found = collect(app(PreRunChecker::class)->run($period))->firstWhere('code', $code);
        $this->assertInstanceOf(PreRunCheck::class, $found, "Check {$code} was not returned.");

        return $found;
    }

    private function requestAdvance(Employment $employment, PayPeriod $period, User $officer): SalaryAdvance
    {
        return app(AdvanceService::class)->request($employment, $period, '10000.00', 'Urgent need', $officer);
    }

    private function requestLoan(Employment $employment, User $officer): Loan
    {
        return app(LoanService::class)->request($employment, [
            'loan_type_code' => 'STAFF',
            'amount' => '5000.00',
            'instalments' => 1,
            'first_month' => '2026-12-01',
            'reason' => 'Test loan for pay run',
        ], $officer);
    }

    public function test_checks_come_back_in_code_order_with_the_documented_blocking_flags(): void
    {
        $company = $this->company();
        $period = $this->december($company);

        $checks = app(PreRunChecker::class)->run($period);

        $this->assertSame(
            ['PR01', 'PR02', 'PR03', 'PR04', 'PR05', 'PR06', 'PR07', 'PR08', 'PR09', 'PR10'],
            array_map(fn (PreRunCheck $c) => $c->code, $checks),
        );
        $this->assertSame(
            [true, true, true, true, true, true, false, false, false, false],
            array_map(fn (PreRunCheck $c) => $c->blocking, $checks),
        );
    }

    public function test_pr01_the_periods_time_data_is_not_closed(): void
    {
        $company = $this->company();
        $period = $this->december($company);

        $open = $this->check($period, 'PR01');
        $this->assertFalse($open->ok);
        $this->assertTrue($open->blocking);
        $this->assertSame(1, $open->count);

        $closed = $this->closedPeriod($company);

        $after = $this->check($closed, 'PR01');
        $this->assertTrue($after->ok);
        $this->assertSame(0, $after->count);
    }

    public function test_pr02_the_previous_periods_pay_run_is_not_locked(): void
    {
        $company = $this->company();
        $officer = $this->officer($company);

        $november = $this->closedPeriod($company, '2026-11');
        $december = $this->december($company);

        $novemberRun = app(PayRunService::class)->open($november, $officer);

        $notLocked = $this->check($december, 'PR02');
        $this->assertFalse($notLocked->ok);
        $this->assertTrue($notLocked->blocking);
        $this->assertSame(1, $notLocked->count);

        PayRun::query()->whereKey($novemberRun->id)->update(['status' => PayRunStatus::LOCKED->value]);

        $locked = $this->check($december, 'PR02');
        $this->assertTrue($locked->ok);
        $this->assertSame(0, $locked->count);
    }

    public function test_pr02_is_skipped_for_the_first_run_of_a_company(): void
    {
        $companyA = $this->company();
        $companyB = $this->company();
        $officerB = $this->officer($companyB);

        // Company A has a closed November and a December period, but no pay run at all.
        $this->closedPeriod($companyA, '2026-11');
        $decemberA = $this->december($companyA);

        // Company B already has an unlocked November run; that must not affect company A.
        $novemberB = $this->closedPeriod($companyB, '2026-11');
        $decemberB = $this->december($companyB);
        app(PayRunService::class)->open($novemberB, $officerB);

        $first = $this->check($decemberA, 'PR02');
        $this->assertTrue($first->ok);
        $this->assertSame(0, $first->count);

        $second = $this->check($decemberB, 'PR02');
        $this->assertFalse($second->ok);
        $this->assertSame(1, $second->count);
    }

    public function test_pr03_pay_rules_are_incomplete(): void
    {
        $company = $this->company();
        $period = $this->december($company);

        $missing = $this->check($period, 'PR03');
        $this->assertFalse($missing->ok);
        $this->assertTrue($missing->blocking);
        $this->assertSame(1, $missing->count);

        $this->seed(PayRuleSeeder::class);

        $complete = $this->check($period, 'PR03');
        $this->assertTrue($complete->ok);
        $this->assertSame(0, $complete->count);
    }

    public function test_pr04_employees_without_compensation_on_the_periods_last_day(): void
    {
        $company = $this->company();
        $employment = $this->hired('2026-01-01', $company);
        $period = $this->closedPeriod($company);
        $this->summary($employment, $period);

        $missing = $this->check($period, 'PR04');
        $this->assertFalse($missing->ok);
        $this->assertTrue($missing->blocking);
        $this->assertSame(1, $missing->count);
        $this->assertSame([$employment->id], $missing->employmentIds);

        app(CompensationService::class)->set($employment, '2026-01-01', [
            'basic_salary' => '60000.00',
            'ot_rate_mode' => OtRateMode::CALCULATED,
        ]);

        $present = $this->check($period, 'PR04');
        $this->assertTrue($present->ok);
        $this->assertSame(0, $present->count);
    }

    public function test_pr05_grades_in_the_run_without_an_extra_day_method(): void
    {
        $employment = $this->paid();
        $officer = $this->officer($employment->company);
        $period = $this->closedPeriod($employment->company);
        $this->summary($employment, $period);

        $missing = $this->check($period, 'PR05');
        $this->assertFalse($missing->ok);
        $this->assertTrue($missing->blocking);
        $this->assertSame(1, $missing->count);
        $this->assertSame([(int) $this->categoryFor($employment)->id], $missing->employmentIds);

        $this->hourly($employment, $officer);

        $present = $this->check($period, 'PR05');
        $this->assertTrue($present->ok);
        $this->assertSame(0, $present->count);
    }

    public function test_pr06_epf_liable_employees_without_an_epf_number(): void
    {
        $employment = $this->paid();
        // The database refuses NULL for an EPF-liable employee, so a blank number is the failing case.
        Employment::query()->whereKey($employment->id)->update(['epf_no' => '']);
        $period = $this->closedPeriod($employment->company);
        $this->summary($employment, $period);

        $missing = $this->check($period, 'PR06');
        $this->assertFalse($missing->ok);
        $this->assertTrue($missing->blocking);
        $this->assertSame(1, $missing->count);
        $this->assertSame([$employment->id], $missing->employmentIds);

        Employment::query()->whereKey($employment->id)->update(['epf_no' => '912345']);

        $present = $this->check($period, 'PR06');
        $this->assertTrue($present->ok);
        $this->assertSame(0, $present->count);
    }

    public function test_pr06_does_not_count_employees_who_are_not_epf_liable(): void
    {
        $employment = $this->paid();
        Employment::query()->whereKey($employment->id)->update(['epf_no' => null, 'epf_liable' => false]);
        $period = $this->closedPeriod($employment->company);
        $this->summary($employment, $period);

        $check = $this->check($period, 'PR06');
        $this->assertTrue($check->ok);
        $this->assertSame(0, $check->count);
    }

    public function test_pr07_employees_with_an_approval_still_pending(): void
    {
        $company = $this->company();
        $officer = $this->officer($company);
        $approver = $this->approver($company);
        $december = $this->december($company);

        $withAdvance = $this->paid('2026-01-01', $company);
        $withLoan = $this->paid('2026-01-01', $company);
        $clean = $this->paid('2026-01-01', $company);

        $advance = $this->requestAdvance($withAdvance, $december, $officer);
        $loan = $this->requestLoan($withLoan, $officer);

        $period = $this->closedPeriod($company);
        foreach ([$withAdvance, $withLoan, $clean] as $employment) {
            $this->summary($employment, $period);
        }

        $pending = $this->check($period, 'PR07');
        $this->assertFalse($pending->ok);
        $this->assertFalse($pending->blocking);
        $this->assertSame(2, $pending->count);
        $this->assertEqualsCanonicalizing([$withAdvance->id, $withLoan->id], $pending->employmentIds);

        $approvals = app(ApprovalService::class);
        $approvals->approve($advance->approvalRequest, $approver);
        $approvals->approve($loan->approvalRequest, $approver);

        $decided = $this->check($period, 'PR07');
        $this->assertTrue($decided->ok);
        $this->assertSame(0, $decided->count);
    }

    public function test_pr08_employees_without_a_bank_account_who_are_not_marked_cash(): void
    {
        $company = $this->company();
        $noBank = $this->paid('2026-01-01', $company);
        $cash = $this->paid('2026-01-01', $company);
        app(BankAccountService::class)->set($cash, ['effective_from' => '2026-01-01', 'is_cash' => true]);

        $period = $this->closedPeriod($company);
        $this->summary($noBank, $period);
        $this->summary($cash, $period);

        $missing = $this->check($period, 'PR08');
        $this->assertFalse($missing->ok);
        $this->assertFalse($missing->blocking);
        $this->assertSame(1, $missing->count);
        $this->assertSame([$noBank->id], $missing->employmentIds);

        app(BankAccountService::class)->set($noBank, $this->account(['effective_from' => '2026-01-01']));

        $present = $this->check($period, 'PR08');
        $this->assertTrue($present->ok);
        $this->assertSame(0, $present->count);
    }

    public function test_pr09_approved_advances_not_yet_marked_paid(): void
    {
        $company = $this->company();
        $officer = $this->officer($company);
        $approver = $this->approver($company);
        $employment = $this->paid('2026-01-01', $company);
        $december = $this->december($company);

        $advance = $this->requestAdvance($employment, $december, $officer);
        app(ApprovalService::class)->approve($advance->approvalRequest, $approver);

        $period = $this->closedPeriod($company);
        $this->summary($employment, $period);

        $unpaid = $this->check($period, 'PR09');
        $this->assertFalse($unpaid->ok);
        $this->assertFalse($unpaid->blocking);
        $this->assertSame(1, $unpaid->count);

        app(AdvanceService::class)->markPaid($advance->fresh(), '2026-12-10', null, $officer);

        $paid = $this->check($period, 'PR09');
        $this->assertTrue($paid->ok);
        $this->assertSame(0, $paid->count);
    }

    public function test_pr10_readiness_issues_of_blocking_severity(): void
    {
        $withIssues = $this->hired();
        $withIssuesPeriod = $this->december($withIssues->company);

        $issues = $this->check($withIssuesPeriod, 'PR10');
        $this->assertFalse($issues->ok);
        $this->assertFalse($issues->blocking);
        $this->assertSame(1, $issues->count);
        $this->assertSame([$withIssues->id], $issues->employmentIds);

        $empty = $this->company();
        $emptyPeriod = $this->december($empty);

        $none = $this->check($emptyPeriod, 'PR10');
        $this->assertTrue($none->ok);
        $this->assertSame(0, $none->count);
    }

    public function test_calculate_is_blocked_by_a_blocking_check_and_not_by_a_warning(): void
    {
        $employment = $this->paid();
        $company = $employment->company;
        $officer = $this->officer($company);
        $period = $this->closedPeriod($company);
        $this->summary($employment, $period);

        $runs = app(PayRunService::class);
        $run = $runs->open($period, $officer);

        // PR05 (blocking): the grade has no extra-day method yet.
        try {
            $runs->calculate($run, $officer);
            $this->fail('Expected PayRunBlocked.');
        } catch (PayRunBlocked $e) {
            $this->assertSame(['PR05'], array_map(fn (PreRunCheck $c) => $c->code, $e->checks));
            $this->assertTrue($e->checks[0]->blocking);
            $this->assertFalse($e->checks[0]->ok);
        }
        $this->assertSame(PayRunStatus::DRAFT, $run->fresh()->status);
        $this->assertSame(0, PayRunEmployee::query()->count());

        $this->hourly($employment, $officer);

        // PR08 (warning): there is still no bank account, and PR10 still has blockers.
        $warning = $this->check($period, 'PR08');
        $this->assertFalse($warning->ok);
        $this->assertFalse($warning->blocking);

        $calculated = $runs->calculate($run->fresh(), $officer);

        $this->assertSame(PayRunStatus::CALCULATED, $calculated->status);
        $this->assertSame(1, PayRunEmployee::query()->where('pay_run_id', $run->id)->count());
    }
}
