<?php

namespace Modules\Payroll\Reports;

use Illuminate\Support\Facades\Auth;
use Modules\Payroll\Models\PayRun;

final class CarriedForwardReport
{
    public function __construct(private readonly ReportAccess $access) {}

    public function build(PayRun $run): array
    {
        $user = Auth::user();
        $this->access->assertCanView($run, $user);
        $this->access->assertDraftable($run);

        $rows = [];
        $totals = [
            'brought_in' => '0.00',
            'recovered' => '0.00',
            'new_shortfall' => '0.00',
            'balance_after' => '0.00',
        ];

        foreach ($this->access->okEmployees($run) as $employee) {
            $in = $this->access->money((string) $employee->carried_forward_in);
            $out = $this->access->money((string) $employee->carried_forward_out);
            if (bccomp($in, '0.00', 2) === 0 && bccomp($out, '0.00', 2) === 0) {
                continue;
            }

            $recovered = $this->access->lineAmount($employee, 'CF');
            $balance = bcadd(bcsub($in, $recovered, 2), $out, 2);

            $row = [
                'employment_id' => $employee->employment_id,
                'employee_no' => $employee->employee_no,
                'name' => $employee->name,
                'brought_in' => $in,
                'recovered' => $recovered,
                'new_shortfall' => $out,
                'balance_after' => $this->access->money($balance),
            ];
            $rows[] = $row;
            foreach (['brought_in', 'recovered', 'new_shortfall', 'balance_after'] as $key) {
                $totals[$key] = bcadd($totals[$key], $row[$key], 2);
            }
        }

        foreach ($totals as $key => $value) {
            $totals[$key] = $this->access->money($value);
        }

        $period = $run->payPeriod;
        $label = sprintf('Carried forward — %04d-%02d', (int) $period->year, (int) $period->month);

        return [
            'title' => $label.$this->access->draftSuffix($run),
            'rows' => $rows,
            'totals' => $totals,
        ];
    }
}
