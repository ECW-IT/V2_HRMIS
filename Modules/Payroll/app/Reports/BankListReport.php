<?php

namespace Modules\Payroll\Reports;

use Illuminate\Support\Facades\Auth;
use Modules\CoreHr\Models\Bank;
use Modules\CoreHr\Models\BankBranch;
use Modules\Payroll\Models\PayRun;
use Modules\Payroll\Models\PayRunEmployee;

final class BankListReport
{
    public function __construct(private readonly ReportAccess $access) {}

    public function build(PayRun $run): array
    {
        $user = Auth::user();
        $this->access->assertCanView($run, $user);
        $this->access->assertCanExport($run, $user);
        $this->access->assertApproved($run);

        $period = $run->payPeriod;
        $ym = sprintf('%04d-%02d', (int) $period->year, (int) $period->month);

        $bank = [];
        $cash = [];
        $zero = [];

        foreach ($this->access->okEmployees($run) as $employee) {
            $net = $this->access->money((string) $employee->net_pay);
            if (bccomp($net, '0.00', 2) === 0) {
                $zero[] = $this->row($employee, $ym, $net);

                continue;
            }

            if ($employee->is_cash || $employee->account_no === null || $employee->account_no === '') {
                $cash[] = $this->row($employee, $ym, $net);
            } else {
                $bank[] = $this->row($employee, $ym, $net);
            }
        }

        return [
            'bank' => $bank,
            'cash' => $cash,
            'zero' => $zero,
            'totals' => [
                'bank' => ['count' => count($bank), 'amount' => $this->sumAmount($bank)],
                'cash' => ['count' => count($cash), 'amount' => $this->sumAmount($cash)],
                'zero' => ['count' => count($zero), 'amount' => $this->sumAmount($zero)],
            ],
        ];
    }

    private function row(PayRunEmployee $employee, string $ym, string $net): array
    {
        $bank = $employee->bank_id ? Bank::query()->find($employee->bank_id) : null;
        $branch = $employee->bank_branch_id ? BankBranch::query()->find($employee->bank_branch_id) : null;

        return [
            'employment_id' => $employee->employment_id,
            'employee_no' => $employee->employee_no,
            'name' => $employee->name,
            'bank_code' => $bank?->code,
            'branch_code' => $branch?->code,
            'account_no' => $employee->account_no,
            'account_name' => $employee->account_name,
            'amount' => $net,
            'reference' => sprintf('SAL %s %s', $ym, $employee->employee_no),
            'is_cash' => (bool) $employee->is_cash,
        ];
    }

    /** @param  list<array<string, mixed>>  $rows */
    private function sumAmount(array $rows): string
    {
        $sum = '0.00';
        foreach ($rows as $row) {
            $sum = bcadd($sum, (string) $row['amount'], 2);
        }

        return $this->access->money($sum);
    }
}
