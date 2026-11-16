<?php

namespace Modules\Payroll\Reports;

use Illuminate\Support\Facades\Auth;
use Modules\CoreHr\Models\Employment;
use Modules\Payroll\Models\PayRun;
use Modules\Payroll\Models\PayRunEmployee;

final class EpfReport
{
    public function __construct(private readonly ReportAccess $access) {}

    public function build(PayRun $run): array
    {
        $user = Auth::user();
        $this->access->assertCanView($run, $user);
        $this->access->assertCanExport($run, $user);
        $this->access->assertApproved($run);

        $rows = [];
        $totals = ['epf_base' => '0.00', 'employee' => '0.00', 'employer' => '0.00', 'total' => '0.00'];

        foreach ($this->access->okEmployees($run) as $employee) {
            $ee = $this->access->lineAmount($employee, 'EPF_EE');
            $er = $this->access->lineAmount($employee, 'EPF_ER');
            if (bccomp($ee, '0.00', 2) === 0 && bccomp($er, '0.00', 2) === 0) {
                continue;
            }

            $total = bcadd($ee, $er, 2);
            $row = [
                'employment_id' => $employee->employment_id,
                'epf_no' => $employee->epf_no,
                'nic' => $this->nic($employee),
                'name' => $employee->name,
                'epf_base' => $this->access->money((string) $employee->epf_base),
                'employee' => $ee,
                'employer' => $er,
                'total' => $this->access->money($total),
            ];
            $rows[] = $row;
            foreach (['epf_base', 'employee', 'employer', 'total'] as $key) {
                $totals[$key] = bcadd($totals[$key], $row[$key], 2);
            }
        }

        foreach ($totals as $key => $value) {
            $totals[$key] = $this->access->money($value);
        }

        return ['rows' => $rows, 'totals' => $totals];
    }

    private function nic(PayRunEmployee $employee): ?string
    {
        return Employment::query()->with('person')->find($employee->employment_id)?->person?->nic;
    }
}
