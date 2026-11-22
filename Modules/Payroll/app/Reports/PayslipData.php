<?php

namespace Modules\Payroll\Reports;

use Illuminate\Support\Facades\Auth;
use Modules\CoreHr\Models\Bank;
use Modules\CoreHr\Models\BankBranch;
use Modules\CoreHr\Models\Employment;
use Modules\Organisation\Models\Company;
use Modules\Organisation\Models\Designation;
use Modules\Organisation\Models\OrgUnit;
use Modules\Payroll\Enums\ComponentKind;
use Modules\Payroll\Enums\FundCode;
use Modules\Payroll\Enums\LoanStatus;
use Modules\Payroll\Models\Loan;
use Modules\Payroll\Models\PayComponent;
use Modules\Payroll\Models\PayRunEmployee;
use Modules\Payroll\Services\FundService;
use Modules\Payroll\Services\LoanService;
use Modules\Payroll\Services\YtdService;
use Modules\Payroll\Support\TaxYear;

final class PayslipData
{
    public function __construct(
        private readonly ReportAccess $access,
        private readonly LoanService $loans,
        private readonly FundService $funds,
        private readonly YtdService $ytd,
    ) {}

    /** Everything the payslip shows for one employee in one run. */
    public function for(PayRunEmployee $e): array
    {
        $run = $e->payRun;
        $user = Auth::user();
        $this->access->assertCanView($run, $user);
        $this->access->assertApproved($run);

        $e->loadMissing('lines');
        $period = $run->payPeriod;
        $company = Company::query()->find($run->company_id);
        $employment = Employment::query()->with('person')->find($e->employment_id);

        $earnings = [];
        $deductions = [];
        $lines = $e->lines
            ->filter(fn ($line) => in_array($line->kind, [ComponentKind::EARNING, ComponentKind::DEDUCTION], true))
            ->sortBy('display_order')
            ->values();

        $names = PayComponent::query()
            ->whereIn('code', $lines->pluck('code')->unique()->all())
            ->pluck('name', 'code');

        foreach ($lines as $line) {
            $item = [
                'code' => $line->code,
                'name' => $names[$line->code] ?? $line->code,
                'quantity' => $line->quantity !== null ? $this->access->money((string) $line->quantity) : null,
                'rate' => $line->rate !== null ? $this->access->money((string) $line->rate) : null,
                'amount' => $this->access->money((string) $line->amount),
                'is_override' => (bool) $line->is_override,
            ];
            if ($line->kind === ComponentKind::EARNING) {
                $earnings[] = $item;
            } else {
                $deductions[] = $item;
            }
        }

        $bank = $e->bank_id ? Bank::query()->find($e->bank_id) : null;
        $branch = $e->bank_branch_id ? BankBranch::query()->find($e->bank_branch_id) : null;

        $loanBalances = [];
        foreach (Loan::query()->where('employment_id', $e->employment_id)->whereIn('status', [LoanStatus::ACTIVE, LoanStatus::SETTLED])->get() as $loan) {
            $loanBalances[] = [
                'loan_id' => $loan->id,
                'outstanding' => $this->loans->outstanding($loan),
                'status' => $loan->status->value,
            ];
        }

        $fundBalances = [];
        foreach (FundCode::cases() as $fund) {
            $balance = $this->funds->balance($e->employment_id, $fund);
            if (bccomp($balance, '0.00', 2) !== 0) {
                $fundBalances[] = ['fund' => $fund->value, 'balance' => $balance];
            }
        }

        $taxYearStart = TaxYear::startFor($period->end_date);
        $ytd = $this->ytd->for((int) $e->employment_id, $taxYearStart, $period);

        $otHours = number_format(((int) $e->ot_minutes) / 60, 2, '.', '');
        $gross = bcadd((string) $e->gross_sheet1, (string) $e->gross_sheet2, 2);

        return [
            'company_name' => $company?->name,
            'period' => sprintf('%04d-%02d', (int) $period->year, (int) $period->month),
            'period_label' => sprintf('%04d-%02d', (int) $period->year, (int) $period->month),
            'employee_no' => $e->employee_no,
            'name' => $e->name,
            'job' => $e->designation?->name ?? Designation::query()->find($e->designation_id)?->name,
            'org_unit' => OrgUnit::query()->find($e->org_unit_id)?->name,
            'epf_no' => $e->epf_no,
            'nic' => $employment?->person?->nic,
            'is_cash' => (bool) $e->is_cash,
            'bank_name' => $bank?->name,
            'bank_branch' => $branch?->name,
            'account_no_masked' => $this->maskAccount($e->account_no),
            'account_name' => $e->account_name,
            'expected_days' => $this->access->money((string) $e->expected_days),
            'credited_days' => $this->access->money((string) $e->credited_days),
            'nopay_days' => $this->access->money((string) $e->nopay_days),
            'late_minutes_chargeable' => (int) $e->late_minutes_chargeable,
            'ot_hours' => $otHours,
            'earnings' => $earnings,
            'deductions' => $deductions,
            'gross' => $this->access->money($gross),
            'total_deductions' => $this->access->money((string) $e->total_deductions),
            'net_pay' => $this->access->money((string) $e->net_pay),
            'carried_forward_out' => $this->access->money((string) $e->carried_forward_out),
            'employer_epf' => $this->access->lineAmount($e, 'EPF_ER'),
            'employer_etf' => $this->access->lineAmount($e, 'ETF_ER'),
            'loans' => $loanBalances,
            'funds' => $fundBalances,
            'ytd_tax' => $ytd->tax,
            'ytd_epf_employee' => $ytd->epfEmployee,
            'filename' => sprintf('payslip-%s-%04d-%02d.pdf', $e->employee_no, (int) $period->year, (int) $period->month),
        ];
    }

    private function maskAccount(?string $accountNo): ?string
    {
        if ($accountNo === null || $accountNo === '') {
            return null;
        }
        $len = strlen($accountNo);
        if ($len <= 4) {
            return $accountNo;
        }

        return str_repeat('*', $len - 4).substr($accountNo, -4);
    }
}
