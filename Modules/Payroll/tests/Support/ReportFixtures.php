<?php

namespace Modules\Payroll\Tests\Support;

use App\Models\User;
use Database\Seeders\ApprovalDefinitionSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Gate;
use Modules\Calendar\Models\PayPeriod;
use Modules\Calendar\Services\PayPeriodService;
use Modules\CoreHr\Models\Employment;
use Modules\CoreHr\Services\BankAccountService;
use Modules\Organisation\Models\Company;
use Modules\Payroll\Database\Seeders\LoanTypeSeeder;
use Modules\Payroll\Enums\ExtraDayMethod;
use Modules\Payroll\Models\PayRun;
use Modules\Payroll\Models\PayRunEmployee;
use Modules\Payroll\Services\AdvanceService;
use Modules\Payroll\Services\LoanService;
use Modules\Payroll\Services\PayEntryService;
use Modules\Payroll\Services\PayRuleService;
use Modules\Payroll\Services\PayRunService;
use Modules\Workflow\Services\ApprovalService;
use Tests\Support\HrFixtures;

/**
 * Shared December 2026 locked run with employees A (standard), B (tax), C (carry-forward cash).
 */
trait ReportFixtures
{
    use HrFixtures;

    protected Company $company;

    protected User $officer;

    protected User $approver;

    protected User $viewer;

    protected Employment $employeeA;

    protected Employment $employeeB;

    protected Employment $employeeC;

    protected PayPeriod $period;

    protected PayRun $run;

    protected function seedReportContext(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(ApprovalDefinitionSeeder::class);
        $this->seed(LoanTypeSeeder::class);
        $this->travelTo('2026-12-15');

        $this->company = $this->company();
        $this->officer = $this->userWith('payroll-officer');
        $this->approver = $this->userWith('hr-manager');
        $this->viewer = $this->userWith('payroll-officer');
    }

    protected function userWith(string $role): User
    {
        return User::factory()
            ->forCompanies([$this->company])
            ->withRole($role)
            ->withPermissions('approval.act')
            ->create(['must_change_password' => false]);
    }

    protected function hourly(Employment $employment): void
    {
        $this->actingAs($this->officer);
        Gate::authorize('payroll.config');
        $grade = $employment->assignmentOn('2026-12-31')->grade;
        app(PayRuleService::class)->setExtraDayMethod($grade, ExtraDayMethod::HOURLY, '2026-01-01');
    }

    protected function december(): PayPeriod
    {
        return app(PayPeriodService::class)->ensure($this->company, 2026, 12);
    }

    /** Standard example A: net 57,217.00 with bank account. */
    protected function makeEmployeeA(): Employment
    {
        $e = $this->paid('2026-01-01', $this->company, ['attendance_tier_code' => 'T1'], [
            'PERF' => '10000.00',
            'FUEL' => '5000.00',
        ]);
        $this->hourly($e);
        app(BankAccountService::class)->set($e, $this->account(['effective_from' => '2026-01-01']));

        $period = $this->december();
        app(PayEntryService::class)->add($e, $period, ['component_code' => 'MEALS', 'amount' => '3000.00'], $this->officer);

        $advance = app(AdvanceService::class)->request($e, $period, '10000.00', 'Urgent need', $this->officer);
        app(ApprovalService::class)->approve($advance->approvalRequest, $this->approver);
        app(AdvanceService::class)->markPaid($advance->fresh(), '2026-12-10', null, $this->officer);

        $loan = app(LoanService::class)->request($e, [
            'loan_type_code' => 'STAFF',
            'amount' => '10000.00',
            'instalments' => 2,
            'first_month' => '2026-12-01',
            'reason' => 'Test loan for pay run',
        ], $this->officer);
        app(ApprovalService::class)->approve($loan->approvalRequest, $this->approver);
        app(LoanService::class)->markPaid($loan->fresh(), '2026-12-01', null, $this->officer);

        return $e->fresh();
    }

    /** Tax example B: basic 250k, FUEL 30k, PIECE_RATE 50k → net 283,500.00. */
    protected function makeEmployeeB(): Employment
    {
        $e = $this->paid('2026-01-01', $this->company, ['basic_salary' => '250000.00'], [
            'FUEL' => '30000.00',
        ]);
        $this->hourly($e);
        app(BankAccountService::class)->set($e, $this->account(['effective_from' => '2026-01-01', 'account_no' => '9988776655', 'account_name' => 'Tax Employee']));

        $period = $this->december();
        app(PayEntryService::class)->add($e, $period, ['component_code' => 'PIECE_RATE', 'amount' => '50000.00'], $this->officer);

        return $e->fresh();
    }

    /** Carry-forward example C: net 0, CF out 4,600, cash. */
    protected function makeEmployeeC(): Employment
    {
        config(['payroll.advance_limit_percent' => 100]);

        $e = $this->paid('2026-01-01', $this->company, ['basic_salary' => '20000.00']);
        $this->hourly($e);
        app(BankAccountService::class)->set($e, ['effective_from' => '2026-01-01', 'is_cash' => true]);

        $period = $this->december();
        $advance = app(AdvanceService::class)->request($e, $period, '15000.00', 'Urgent need', $this->officer);
        app(ApprovalService::class)->approve($advance->approvalRequest, $this->approver);
        app(AdvanceService::class)->markPaid($advance->fresh(), '2026-12-10', null, $this->officer);

        $loan = app(LoanService::class)->request($e, [
            'loan_type_code' => 'STAFF',
            'amount' => '8000.00',
            'instalments' => 1,
            'first_month' => '2026-12-01',
            'reason' => 'Test loan for carry forward',
        ], $this->officer);
        app(ApprovalService::class)->approve($loan->approvalRequest, $this->approver);
        app(LoanService::class)->markPaid($loan->fresh(), '2026-12-01', null, $this->officer);

        return $e->fresh();
    }

    protected function buildLockedRun(): PayRun
    {
        $this->travelTo('2026-12-15');
        $this->employeeA = $this->makeEmployeeA();
        $this->employeeB = $this->makeEmployeeB();
        $this->employeeC = $this->makeEmployeeC();

        $this->period = $this->closedPeriod($this->company);
        $this->summary($this->employeeA, $this->period, [
            'credited_days' => 24,
            'late_minutes_chargeable' => 30,
            'ot_normal_minutes' => 600,
        ]);
        $this->summary($this->employeeB, $this->period);
        $this->summary($this->employeeC, $this->period);

        $runs = app(PayRunService::class);
        $run = $runs->open($this->period, $this->officer);
        $runs->calculate($run, $this->officer);
        $run = $runs->approve($run->fresh(), $this->approver);
        $this->run = $runs->lock($run->fresh(), $this->officer);

        return $this->run;
    }

    protected function buildCalculatedRun(): PayRun
    {
        $this->travelTo('2026-12-15');
        $this->employeeA = $this->makeEmployeeA();
        $this->employeeB = $this->makeEmployeeB();
        $this->employeeC = $this->makeEmployeeC();

        $this->period = $this->closedPeriod($this->company);
        $this->summary($this->employeeA, $this->period, [
            'credited_days' => 24,
            'late_minutes_chargeable' => 30,
            'ot_normal_minutes' => 600,
        ]);
        $this->summary($this->employeeB, $this->period);
        $this->summary($this->employeeC, $this->period);

        $runs = app(PayRunService::class);
        $run = $runs->open($this->period, $this->officer);
        $this->run = $runs->calculate($run, $this->officer);

        return $this->run;
    }

    protected function row(Employment $e, ?PayRun $run = null): PayRunEmployee
    {
        return PayRunEmployee::query()
            ->where('pay_run_id', ($run ?? $this->run)->id)
            ->where('employment_id', $e->id)
            ->firstOrFail();
    }

    protected function actingAsViewer(): void
    {
        $this->actingAs($this->viewer);
    }
}
