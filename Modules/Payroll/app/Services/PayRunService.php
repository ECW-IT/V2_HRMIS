<?php

namespace Modules\Payroll\Services;

use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Modules\Attendance\Models\PeriodTimeSummary;
use Modules\Attendance\Services\SummaryService;
use Modules\Calendar\Models\PayPeriod;
use Modules\CoreHr\Models\Employment;
use Modules\Organisation\Models\Company;
use Modules\Payroll\Calculator\ComponentSpec;
use Modules\Payroll\Calculator\PayCalculator;
use Modules\Payroll\Enums\AdvanceStatus;
use Modules\Payroll\Enums\InstalmentStatus;
use Modules\Payroll\Enums\LoanStatus;
use Modules\Payroll\Enums\PayRunEmployeeStatus;
use Modules\Payroll\Enums\PayRunStatus;
use Modules\Payroll\Exceptions\InvalidPayRunAction;
use Modules\Payroll\Exceptions\PayCalculationError;
use Modules\Payroll\Exceptions\PayRunBlocked;
use Modules\Payroll\Jobs\CalculatePayRun;
use Modules\Payroll\Models\Loan;
use Modules\Payroll\Models\LoanInstalment;
use Modules\Payroll\Models\PayComponent;
use Modules\Payroll\Models\PayRun;
use Modules\Payroll\Models\PayRunEmployee;
use Modules\Payroll\Models\PayRunEvent;
use Modules\Payroll\Models\PayRunLine;
use Modules\Payroll\Models\SalaryAdvance;
use Modules\Payroll\Run\InputBuilder;
use Modules\Payroll\Run\PreRunChecker;

final class PayRunService
{
    public function __construct(
        private readonly PreRunChecker $checker,
        private readonly InputBuilder $builder,
        private readonly PayCalculator $calculator,
        private readonly SummaryService $summaries,
        private readonly PayRuleService $payRules,
        private readonly PayComponentService $components,
        private readonly ArrearsService $arrears,
        private readonly FundService $funds,
    ) {}

    public function forPeriod(PayPeriod $p): ?PayRun
    {
        return PayRun::query()->where('pay_period_id', $p->id)->first();
    }

    public function open(PayPeriod $p, User $by): PayRun
    {
        $this->authorize($by, 'payrun.manage', (int) $p->company_id);

        $existing = $this->forPeriod($p);
        if ($existing !== null) {
            return $existing;
        }

        return PayRun::query()->create([
            'company_id' => $p->company_id,
            'pay_period_id' => $p->id,
            'status' => PayRunStatus::DRAFT,
        ]);
    }

    public function calculate(PayRun $run, User $by): PayRun
    {
        $this->authorize($by, 'payrun.manage', (int) $run->company_id);
        $this->assertStatus($run, [PayRunStatus::DRAFT, PayRunStatus::CALCULATED]);

        $period = $run->payPeriod;
        $checks = $this->checker->run($period);
        $blocking = array_values(array_filter($checks, fn ($c) => $c->blocking && ! $c->ok));
        if ($blocking !== []) {
            throw new PayRunBlocked($blocking);
        }

        $count = $this->summaries->final($period)->count();
        if ($count > 50) {
            CalculatePayRun::dispatch($run->id, $by->id);

            return $run->fresh();
        }

        return $this->performCalculation($run, $by);
    }

    public function performCalculation(PayRun $run, User $by): PayRun
    {
        return DB::transaction(function () use ($run, $by) {
            $period = $run->payPeriod;
            $company = Company::query()->findOrFail($run->company_id);
            $rules = $this->payRules->rulesFor($company, $period->end_date);
            $components = $this->componentSpecs();
            $summaries = $this->summaries->final($period);

            $run->employees()->each(function (PayRunEmployee $emp) {
                $emp->lines()->delete();
                $emp->delete();
            });

            $errorCount = 0;
            $gross1 = '0.00';
            $gross2 = '0.00';
            $ded = '0.00';
            $net = '0.00';
            $employer = '0.00';
            $cfOut = '0.00';

            foreach ($summaries as $summary) {
                $row = $this->calculateOne($run, $summary, $rules, $components);
                if ($row->status === PayRunEmployeeStatus::ERROR) {
                    $errorCount++;
                } else {
                    $gross1 = bcadd($gross1, (string) $row->gross_sheet1, 2);
                    $gross2 = bcadd($gross2, (string) $row->gross_sheet2, 2);
                    $ded = bcadd($ded, (string) $row->total_deductions, 2);
                    $net = bcadd($net, (string) $row->net_pay, 2);
                    $employer = bcadd($employer, (string) $row->employer_total, 2);
                    $cfOut = bcadd($cfOut, (string) $row->carried_forward_out, 2);
                }
            }

            $version = (int) ($summaries->max('version') ?? $period->close_version);

            $run->fill([
                'status' => PayRunStatus::CALCULATED,
                'time_summary_version' => $version,
                'employee_count' => $summaries->count(),
                'error_count' => $errorCount,
                'gross_sheet1' => $gross1,
                'gross_sheet2' => $gross2,
                'total_deductions' => $ded,
                'net_pay' => $net,
                'employer_total' => $employer,
                'carried_forward_out' => $cfOut,
                'calculated_by' => $by->id,
                'calculated_at' => now(),
            ]);
            $run->save();

            $this->event($run, 'CALCULATED', null, $by);

            return $run->fresh(['employees.lines', 'events']);
        });
    }

    public function recalculateEmployee(PayRun $run, int $employmentId, User $by): PayRunEmployee
    {
        $this->authorize($by, 'payrun.manage', (int) $run->company_id);
        $this->assertStatus($run, [PayRunStatus::CALCULATED, PayRunStatus::DRAFT]);

        return DB::transaction(function () use ($run, $employmentId) {
            $period = $run->payPeriod;
            $company = Company::query()->findOrFail($run->company_id);
            $rules = $this->payRules->rulesFor($company, $period->end_date);
            $components = $this->componentSpecs();
            $summary = $this->summaries->final($period)->firstWhere('employment_id', $employmentId);
            if ($summary === null) {
                throw new InvalidPayRunAction('The employee is not in this pay run.');
            }

            $existing = $run->employees()->where('employment_id', $employmentId)->first();
            if ($existing !== null) {
                $existing->lines()->delete();
                $existing->delete();
            }

            $row = $this->calculateOne($run->fresh(), $summary, $rules, $components);
            $this->refreshRunTotals($run->fresh());

            return $row->fresh(['lines']);
        });
    }

    public function approve(PayRun $run, User $by): PayRun
    {
        $this->authorize($by, 'payrun.approve', (int) $run->company_id);
        $this->assertStatus($run, [PayRunStatus::CALCULATED]);

        if ((int) $run->error_count > 0) {
            throw new InvalidPayRunAction('The pay run still has employees in error.');
        }

        if ((int) $run->calculated_by === (int) $by->id) {
            throw new InvalidPayRunAction('The approver must be different from the calculator.');
        }

        $latest = (int) ($this->summaries->final($run->payPeriod)->max('version') ?? 0);
        if ((int) $run->time_summary_version !== $latest) {
            throw new InvalidPayRunAction('The time data changed. Recalculate.');
        }

        $run->fill([
            'status' => PayRunStatus::APPROVED,
            'approved_by' => $by->id,
            'approved_at' => now(),
        ]);
        $run->save();
        $this->event($run, 'APPROVED', null, $by);

        return $run->fresh();
    }

    public function sendBack(PayRun $run, User $by, string $reason): PayRun
    {
        if (! $by->can('payrun.approve') && ! $by->can('payrun.manage')) {
            throw new AuthorizationException('This action is unauthorized.');
        }
        if (! $this->canAccessCompany($by, (int) $run->company_id)) {
            throw new AuthorizationException('This action is unauthorized.');
        }

        $this->assertStatus($run, [PayRunStatus::APPROVED]);
        $reason = trim($reason);
        if ($reason === '') {
            throw new InvalidPayRunAction('A reason is required.');
        }

        $run->fill([
            'status' => PayRunStatus::CALCULATED,
            'approved_by' => null,
            'approved_at' => null,
        ]);
        $run->save();
        $this->event($run, 'SENT_BACK', $reason, $by);

        return $run->fresh();
    }

    public function lock(PayRun $run, User $by): PayRun
    {
        $this->authorize($by, 'payrun.lock', (int) $run->company_id);
        $this->assertStatus($run, [PayRunStatus::APPROVED]);

        return DB::transaction(function () use ($run, $by) {
            $run = $run->fresh(['employees.lines']);
            $periodId = (int) $run->pay_period_id;

            foreach ($run->employees as $emp) {
                foreach ($emp->lines as $line) {
                    if ($line->code === 'ADVANCE' && $line->source_type === 'salary_advance' && $line->source_id) {
                        SalaryAdvance::query()->where('id', $line->source_id)->update([
                            'status' => AdvanceStatus::RECOVERED,
                            'recovered_pay_run_id' => $run->id,
                        ]);
                    }

                    if ($line->code === 'LOAN' && $line->source_type === 'loan_instalment' && $line->source_id) {
                        $instalment = LoanInstalment::query()->find($line->source_id);
                        if ($instalment !== null) {
                            $instalment->update([
                                'status' => InstalmentStatus::RECOVERED,
                                'pay_run_id' => $run->id,
                            ]);
                            $loan = Loan::query()->find($instalment->loan_id);
                            if ($loan !== null && ! $loan->instalmentRows()->where('status', InstalmentStatus::DUE)->exists()) {
                                $loan->update(['status' => LoanStatus::SETTLED]);
                            }
                        }
                    }

                    if ($line->code === 'CF') {
                        $this->arrears->recordRecovery(
                            (int) $emp->employment_id,
                            (int) $run->id,
                            $periodId,
                            (string) $line->amount,
                        );
                    }

                    $component = PayComponent::query()->where('code', $line->code)->first();
                    if ($component?->fund !== null && $line->kind->value === 'DEDUCTION') {
                        $this->funds->recordContribution(
                            (int) $emp->employment_id,
                            $component->fund,
                            (int) $run->id,
                            $periodId,
                            (string) $line->amount,
                        );
                    }
                }

                if (bccomp((string) $emp->carried_forward_out, '0.00', 2) > 0) {
                    $this->arrears->recordShortfall(
                        (int) $emp->employment_id,
                        (int) $run->id,
                        $periodId,
                        (string) $emp->carried_forward_out,
                    );
                }
            }

            $run->fill([
                'status' => PayRunStatus::LOCKED,
                'locked_by' => $by->id,
                'locked_at' => now(),
            ]);
            $run->save();
            $this->event($run, 'LOCKED', null, $by);

            return $run->fresh();
        });
    }

    public function isPeriodFrozen(int $payPeriodId): bool
    {
        return PayRun::query()
            ->where('pay_period_id', $payPeriodId)
            ->whereIn('status', [PayRunStatus::APPROVED, PayRunStatus::LOCKED])
            ->exists();
    }

    public function isPeriodLocked(int $payPeriodId): bool
    {
        return PayRun::query()
            ->where('pay_period_id', $payPeriodId)
            ->where('status', PayRunStatus::LOCKED)
            ->exists();
    }

    /**
     * @param  array<string, ComponentSpec>  $components
     */
    private function calculateOne(PayRun $run, PeriodTimeSummary $summary, $rules, array $components): PayRunEmployee
    {
        try {
            $built = $this->builder->build($summary, $run, $rules, $components);
            $result = $this->calculator->calculate($built['input']);

            $employee = PayRunEmployee::query()->create([
                'pay_run_id' => $run->id,
                'company_id' => $run->company_id,
                ...$built['snapshot'],
                'status' => PayRunEmployeeStatus::OK,
                'error' => null,
                'gross_sheet1' => $result->grossSheet1,
                'gross_sheet2' => $result->grossSheet2,
                'total_deductions' => $result->totalDeductions,
                'net_pay' => $result->netPay,
                'carried_forward_out' => $result->carriedForwardOut,
                'epf_base' => $result->epfBase,
                'taxable_pay' => $result->taxablePay,
                'employer_total' => $result->employerTotal,
            ]);

            foreach ($result->lines as $line) {
                $component = PayComponent::query()->where('code', $line->code)->firstOrFail();
                PayRunLine::query()->create([
                    'pay_run_employee_id' => $employee->id,
                    'pay_component_id' => $component->id,
                    'code' => $line->code,
                    'kind' => $line->kind,
                    'sheet' => $line->sheet,
                    'quantity' => $line->quantity,
                    'rate' => $line->rate,
                    'amount' => $line->amount,
                    'source_type' => $line->sourceType,
                    'source_id' => $line->sourceId,
                    'is_override' => $line->isOverride,
                    'display_order' => $line->displayOrder,
                ]);
            }

            return $employee->fresh(['lines']);
        } catch (PayCalculationError $ex) {
            return PayRunEmployee::query()->create([
                'pay_run_id' => $run->id,
                'company_id' => $run->company_id,
                'employment_id' => $summary->employment_id,
                'status' => PayRunEmployeeStatus::ERROR,
                'error' => mb_substr($ex->getMessage(), 0, 255),
                'employee_no' => (string) (Employment::query()->find($summary->employment_id)?->employee_no ?? ''),
                'name' => 'Error',
                'grade_id' => (int) $summary->grade_id,
                'org_unit_id' => null,
                'job_id' => null,
                'employment_type' => 'PERMANENT',
                'epf_liable' => false,
                'epf_no' => null,
                'basic_salary' => '0.00',
                'ot_rate' => '0.00',
                'extra_day_method' => null,
                'attendance_tier_code' => null,
                'expected_days' => $this->qty($summary->expected_days),
                'credited_days' => $this->qty($summary->credited_days),
                'extra_days' => $this->qty($summary->extra_days),
                'nopay_days' => $this->qty($summary->nopay_days),
                'late_minutes_chargeable' => (int) $summary->late_minutes_chargeable,
                'ot_minutes' => (int) $summary->ot_normal_minutes,
                'carried_forward_in' => '0.00',
                'is_cash' => false,
                'bank_id' => null,
                'bank_branch_id' => null,
                'account_no' => null,
                'account_name' => null,
                'gross_sheet1' => '0.00',
                'gross_sheet2' => '0.00',
                'total_deductions' => '0.00',
                'net_pay' => '0.00',
                'carried_forward_out' => '0.00',
                'epf_base' => '0.00',
                'taxable_pay' => '0.00',
                'employer_total' => '0.00',
            ]);
        }
    }

    private function refreshRunTotals(PayRun $run): void
    {
        $emps = $run->employees()->get();
        $ok = $emps->where('status', PayRunEmployeeStatus::OK);

        $run->fill([
            'employee_count' => $emps->count(),
            'error_count' => $emps->where('status', PayRunEmployeeStatus::ERROR)->count(),
            'gross_sheet1' => $ok->sum(fn ($e) => (float) $e->gross_sheet1),
            'gross_sheet2' => $ok->sum(fn ($e) => (float) $e->gross_sheet2),
            'total_deductions' => $ok->sum(fn ($e) => (float) $e->total_deductions),
            'net_pay' => $ok->sum(fn ($e) => (float) $e->net_pay),
            'employer_total' => $ok->sum(fn ($e) => (float) $e->employer_total),
            'carried_forward_out' => $ok->sum(fn ($e) => (float) $e->carried_forward_out),
        ]);
        // Format money fields
        foreach (['gross_sheet1', 'gross_sheet2', 'total_deductions', 'net_pay', 'employer_total', 'carried_forward_out'] as $field) {
            $run->{$field} = number_format((float) $run->{$field}, 2, '.', '');
        }
        $run->save();
    }

    /** @return array<string, ComponentSpec> */
    private function componentSpecs(): array
    {
        $out = [];
        foreach ($this->components->catalogue() as $c) {
            $out[$c->code] = new ComponentSpec(
                $c->code,
                $c->kind,
                $c->method,
                (int) $c->sheet,
                (bool) $c->epf_liable,
                (bool) $c->taxable,
                (int) $c->display_order,
                $c->night_base_hours !== null ? (int) $c->night_base_hours : null,
            );
        }

        return $out;
    }

    private function event(PayRun $run, string $event, ?string $note, User $by): void
    {
        $row = new PayRunEvent([
            'pay_run_id' => $run->id,
            'event' => $event,
            'note' => $note,
            'user_id' => $by->id,
            'created_at' => now(),
        ]);
        $row->save();
    }

    /** @param  list<PayRunStatus>  $allowed */
    private function assertStatus(PayRun $run, array $allowed): void
    {
        if ($run->status === PayRunStatus::LOCKED) {
            throw new InvalidPayRunAction('A locked pay run cannot be changed.');
        }
        if (! in_array($run->status, $allowed, true)) {
            throw new InvalidPayRunAction('This action is not allowed in the current status.');
        }
    }

    private function authorize(User $by, string $permission, int $companyId): void
    {
        if (! $by->can($permission) || ! $this->canAccessCompany($by, $companyId)) {
            throw new AuthorizationException('This action is unauthorized.');
        }
    }

    private function canAccessCompany(User $by, int $companyId): bool
    {
        if ($by->all_companies) {
            return true;
        }

        return $by->companies()->where('companies.id', $companyId)->exists();
    }

    private function qty(mixed $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }
}
