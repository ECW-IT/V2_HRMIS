<?php

namespace Modules\Payroll\Reports;

use Illuminate\Support\Facades\Auth;
use Modules\CoreHr\Models\Employment;
use Modules\Payroll\Models\PayRun;

final class EtfReport
{
    public function __construct(private readonly ReportAccess $access) {}

    public function build(PayRun $run): array
    {
        $user = Auth::user();
        $this->access->assertCanView($run, $user);
        $this->access->assertCanExport($run, $user);
        $this->access->assertApproved($run);

        $rows = [];
        $totals = ['epf_base' => '0.00', 'etf' => '0.00'];

        foreach ($this->access->okEmployees($run) as $employee) {
            $ee = $this->access->lineAmount($employee, 'EPF_EE');
            $er = $this->access->lineAmount($employee, 'EPF_ER');
            if (bccomp($ee, '0.00', 2) === 0 && bccomp($er, '0.00', 2) === 0) {
                continue;
            }

            $etf = $this->access->lineAmount($employee, 'ETF_ER');
            $row = [
                'employment_id' => $employee->employment_id,
                'epf_no' => $employee->epf_no,
                'nic' => Employment::query()->with('person')->find($employee->employment_id)?->person?->nic,
                'name' => $employee->name,
                'epf_base' => $this->access->money((string) $employee->epf_base),
                'etf' => $etf,
            ];
            $rows[] = $row;
            $totals['epf_base'] = bcadd($totals['epf_base'], $row['epf_base'], 2);
            $totals['etf'] = bcadd($totals['etf'], $etf, 2);
        }

        foreach ($totals as $key => $value) {
            $totals[$key] = $this->access->money($value);
        }

        return ['rows' => $rows, 'totals' => $totals];
    }
}
