<?php

namespace Modules\Payroll\Reports;

use Illuminate\Support\Facades\Auth;
use Modules\CoreHr\Models\Employment;
use Modules\Payroll\Models\PayRun;

final class TaxScheduleReport
{
    public function __construct(private readonly ReportAccess $access) {}

    public function build(PayRun $run): array
    {
        $user = Auth::user();
        $this->access->assertCanView($run, $user);
        $this->access->assertCanExport($run, $user);
        $this->access->assertApproved($run);

        $rows = [];
        $totals = ['taxable_pay' => '0.00', 'tax' => '0.00'];

        foreach ($this->access->okEmployees($run) as $employee) {
            $tax = $this->access->lineAmount($employee, 'APIT');
            if (bccomp($tax, '0.00', 2) <= 0) {
                continue;
            }

            $row = [
                'employment_id' => $employee->employment_id,
                'nic' => Employment::query()->with('person')->find($employee->employment_id)?->person?->nic,
                'name' => $employee->name,
                'taxable_pay' => $this->access->money((string) $employee->taxable_pay),
                'tax' => $tax,
            ];
            $rows[] = $row;
            $totals['taxable_pay'] = bcadd($totals['taxable_pay'], $row['taxable_pay'], 2);
            $totals['tax'] = bcadd($totals['tax'], $tax, 2);
        }

        foreach ($totals as $key => $value) {
            $totals[$key] = $this->access->money($value);
        }

        return ['rows' => $rows, 'totals' => $totals];
    }
}
