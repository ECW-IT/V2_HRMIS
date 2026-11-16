<?php

namespace Modules\Payroll\Http\Controllers;

use Illuminate\Http\Request;
use Modules\Payroll\Models\PayRun;
use Modules\Payroll\Models\PayRunEmployee;
use Modules\Payroll\Reports\ReportAccess;
use Modules\Payroll\Services\PayslipService;
use Symfony\Component\HttpFoundation\Response;

final class PayslipController
{
    public function __invoke(
        Request $request,
        PayRun $run,
        PayRunEmployee $employee,
        PayslipService $payslips,
        ReportAccess $access,
    ): Response {
        abort_unless($request->user()?->can('payroll.export'), 403);
        abort_unless($access->canAccessCompany($request->user(), (int) $run->company_id), 403);
        abort_unless((int) $employee->pay_run_id === (int) $run->id, 404);

        $period = $run->payPeriod;
        $filename = sprintf(
            'payslip-%s-%04d-%02d.pdf',
            $employee->employee_no,
            (int) $period->year,
            (int) $period->month,
        );

        return $payslips->pdf($employee)->download($filename);
    }
}
