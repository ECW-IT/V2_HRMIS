<?php

namespace Modules\Payroll\Services;

use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DomPdf;
use Illuminate\Support\Facades\Auth;
use Modules\CoreHr\Models\Employment;
use Modules\Payroll\Exceptions\InvalidPayRunAction;
use Modules\Payroll\Models\PayRun;
use Modules\Payroll\Models\PayRunEmployee;
use Modules\Payroll\Reports\PayslipData;
use Modules\Payroll\Reports\ReportAccess;

final class PayslipService
{
    public function __construct(
        private readonly PayslipData $data,
        private readonly ReportAccess $access,
    ) {}

    public function pdf(PayRunEmployee $e): DomPdf
    {
        $user = Auth::user();
        $this->access->assertCanExport($e->payRun, $user);
        $this->assertPayrollActive($e);
        $payload = $this->data->for($e);

        return Pdf::loadView('payroll::pdf.payslip', ['payslips' => [$payload]])
            ->setPaper('a4');
    }

    /** One PDF with a page per employee, in employee-number order. */
    public function pdfForRun(PayRun $run, ?int $orgUnitId = null): DomPdf
    {
        $user = Auth::user();
        $this->access->assertCanExport($run, $user);
        $this->access->assertDraftable($run);

        $payslips = [];
        foreach ($this->access->okEmployees($run, $orgUnitId) as $employee) {
            $employment = Employment::query()->find($employee->employment_id);
            if ($employment === null || ! $employment->payroll_active) {
                continue;
            }
            $payslips[] = $this->data->for($employee);
        }

        return Pdf::loadView('payroll::pdf.payslip', ['payslips' => $payslips])
            ->setPaper('a4');
    }

    private function assertPayrollActive(PayRunEmployee $e): void
    {
        $employment = Employment::query()->find($e->employment_id);
        if ($employment === null || ! $employment->payroll_active) {
            throw new InvalidPayRunAction('Payroll is not active for this employee.');
        }
    }
}
