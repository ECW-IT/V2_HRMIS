<?php

namespace Modules\Payroll\Reports;

use Illuminate\Support\Facades\Auth;
use Modules\Payroll\Enums\ComponentKind;
use Modules\Payroll\Models\PayRun;
use Modules\Payroll\Models\PayRunEmployee;

final class RegisterReport
{
    public function __construct(private readonly ReportAccess $access) {}

    public function build(PayRun $run, ?int $orgUnitId = null): array
    {
        $user = Auth::user();
        $this->access->assertCanView($run, $user);
        $this->access->assertDraftable($run);

        $employees = $this->access->okEmployees($run, $orgUnitId);
        $rows = [];
        $totals = [
            'gross_sheet1' => '0.00',
            'gross_sheet2' => '0.00',
            'gross' => '0.00',
            'total_deductions' => '0.00',
            'net_pay' => '0.00',
            'carried_forward_out' => '0.00',
            'epf_employer' => '0.00',
            'etf' => '0.00',
        ];

        foreach ($employees as $employee) {
            $net1 = $this->sheetNet($employee, 1);
            $net2 = $this->sheetNet($employee, 2);
            $gross = bcadd((string) $employee->gross_sheet1, (string) $employee->gross_sheet2, 2);
            $epfEr = $this->access->lineAmount($employee, 'EPF_ER');
            $etf = $this->access->lineAmount($employee, 'ETF_ER');
            $payment = $employee->is_cash || $employee->account_no === null || $employee->account_no === ''
                ? 'cash'
                : 'bank';

            $row = [
                'employment_id' => $employee->employment_id,
                'employee_no' => $employee->employee_no,
                'name' => $employee->name,
                'gross_sheet1' => $this->access->money((string) $employee->gross_sheet1),
                'gross_sheet2' => $this->access->money((string) $employee->gross_sheet2),
                'gross' => $this->access->money($gross),
                'total_deductions' => $this->access->money((string) $employee->total_deductions),
                'net_pay' => $this->access->money((string) $employee->net_pay),
                'carried_forward_out' => $this->access->money((string) $employee->carried_forward_out),
                'epf_employer' => $epfEr,
                'etf' => $etf,
                'payment_method' => $payment,
                'net_sheet1' => $this->access->money($net1),
                'net_sheet2' => $this->access->money($net2),
            ];
            $rows[] = $row;

            foreach (['gross_sheet1', 'gross_sheet2', 'gross', 'total_deductions', 'net_pay', 'carried_forward_out', 'epf_employer', 'etf'] as $key) {
                $totals[$key] = bcadd($totals[$key], $row[$key], 2);
            }
        }

        foreach ($totals as $key => $value) {
            $totals[$key] = $this->access->money($value);
        }

        $period = $run->payPeriod;
        $label = sprintf('Pay register — %04d-%02d', (int) $period->year, (int) $period->month);

        $errors = $this->access->errorEmployees($run)->map(fn (PayRunEmployee $e) => [
            'employee_no' => $e->employee_no,
            'name' => $e->name,
            'error' => $e->error,
        ])->all();

        return [
            'title' => $label.$this->access->draftSuffix($run),
            'rows' => $rows,
            'totals' => $totals,
            'errors' => $errors,
        ];
    }

    private function sheetNet(PayRunEmployee $employee, int $sheet): string
    {
        $gross = $sheet === 1 ? (string) $employee->gross_sheet1 : (string) $employee->gross_sheet2;
        $deductions = '0.00';
        foreach ($employee->lines as $line) {
            if ((int) $line->sheet === $sheet && $line->kind === ComponentKind::DEDUCTION) {
                $deductions = bcadd($deductions, (string) $line->amount, 2);
            }
        }

        return bcsub($gross, $deductions, 2);
    }
}
