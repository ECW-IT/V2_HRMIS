<?php

namespace Modules\Payroll\Reports;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Modules\Organisation\Models\Job;
use Modules\Payroll\Enums\ComponentKind;
use Modules\Payroll\Models\PayComponent;
use Modules\Payroll\Models\PayRun;
use Modules\Payroll\Models\PayRunEmployee;

final class PaysheetReport
{
    public function __construct(private readonly ReportAccess $access) {}

    /** @param int $sheet 1 or 2. Returns ['columns' => [code => name], 'rows' => [...], 'totals' => [...], 'title' => string]. */
    public function build(PayRun $run, int $sheet, ?int $orgUnitId = null): array
    {
        $user = Auth::user();
        $this->access->assertCanView($run, $user);
        $this->access->assertDraftable($run);

        $employees = $this->access->okEmployees($run, $orgUnitId);
        if ($sheet === 2) {
            $employees = $employees->filter(fn (PayRunEmployee $e) => $e->lines->contains(
                fn ($line) => (int) $line->sheet === 2 && bccomp((string) $line->amount, '0.00', 2) !== 0
            ))->values();
        }

        $componentCodes = $this->nonZeroCodes($employees, $sheet);
        $catalogue = PayComponent::query()->whereIn('code', $componentCodes)->get()->keyBy('code');
        $ordered = $catalogue->sortBy(function (PayComponent $c) {
            $kindOrder = $c->kind === ComponentKind::EARNING ? 0 : 1;

            return sprintf('%d-%06d', $kindOrder, (int) $c->display_order);
        });

        $columns = [];
        foreach ($ordered as $c) {
            $columns[$c->code] = $c->name;
        }

        $rows = [];
        $totals = ['gross' => '0.00', 'deductions' => '0.00', 'net' => '0.00'];
        foreach ($columns as $code => $_) {
            $totals[$code] = '0.00';
        }
        if ($sheet === 1) {
            $totals['expected_days'] = '0.00';
            $totals['credited_days'] = '0.00';
            $totals['ot_hours'] = '0.00';
        }

        foreach ($employees as $employee) {
            $gross = $sheet === 1 ? (string) $employee->gross_sheet1 : (string) $employee->gross_sheet2;
            $deductions = $this->sheetDeductions($employee, $sheet);
            $net = bcsub($gross, $deductions, 2);

            $row = [
                'employment_id' => $employee->employment_id,
                'employee_no' => $employee->employee_no,
                'name' => $employee->name,
                'job' => $employee->job?->name ?? Job::query()->find($employee->job_id)?->name,
            ];

            if ($sheet === 1) {
                $otHours = number_format(((int) $employee->ot_minutes) / 60, 2, '.', '');
                $row['expected_days'] = $this->access->money((string) $employee->expected_days);
                $row['credited_days'] = $this->access->money((string) $employee->credited_days);
                $row['ot_hours'] = $otHours;
                $totals['expected_days'] = bcadd($totals['expected_days'], $row['expected_days'], 2);
                $totals['credited_days'] = bcadd($totals['credited_days'], $row['credited_days'], 2);
                $totals['ot_hours'] = bcadd($totals['ot_hours'], $otHours, 2);
            }

            foreach ($columns as $code => $_) {
                $amount = $this->access->lineAmount($employee, $code);
                $row[$code] = $amount;
                $totals[$code] = bcadd($totals[$code], $amount, 2);
            }

            $row['gross'] = $this->access->money($gross);
            $row['deductions'] = $this->access->money($deductions);
            $row['net'] = $this->access->money($net);

            $totals['gross'] = bcadd($totals['gross'], $gross, 2);
            $totals['deductions'] = bcadd($totals['deductions'], $deductions, 2);
            $totals['net'] = bcadd($totals['net'], $net, 2);

            $rows[] = $row;
        }

        foreach ($totals as $key => $value) {
            $totals[$key] = $this->access->money($value);
        }

        $period = $run->payPeriod;
        $label = sprintf('Paysheet %d — %04d-%02d', $sheet, (int) $period->year, (int) $period->month);

        return [
            'title' => $label.$this->access->draftSuffix($run),
            'columns' => $columns,
            'rows' => $rows,
            'totals' => $totals,
        ];
    }

    /** @param  Collection<int, PayRunEmployee>  $employees */
    private function nonZeroCodes($employees, int $sheet): array
    {
        $codes = [];
        foreach ($employees as $employee) {
            foreach ($employee->lines as $line) {
                if ((int) $line->sheet !== $sheet) {
                    continue;
                }
                if ($line->kind === ComponentKind::EMPLOYER || $line->kind === ComponentKind::INFORMATION) {
                    continue;
                }
                if (bccomp((string) $line->amount, '0.00', 2) === 0) {
                    continue;
                }
                $codes[$line->code] = true;
            }
        }

        return array_keys($codes);
    }

    private function sheetDeductions(PayRunEmployee $employee, int $sheet): string
    {
        $sum = '0.00';
        foreach ($employee->lines as $line) {
            if ((int) $line->sheet !== $sheet) {
                continue;
            }
            if ($line->kind !== ComponentKind::DEDUCTION) {
                continue;
            }
            $sum = bcadd($sum, (string) $line->amount, 2);
        }

        return $sum;
    }
}
