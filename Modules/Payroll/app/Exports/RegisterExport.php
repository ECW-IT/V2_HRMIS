<?php

namespace Modules\Payroll\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

final class RegisterExport implements FromArray, WithHeadings
{
    /** @param  array{rows: list<array<string, mixed>>}  $report */
    public function __construct(private readonly array $report) {}

    public function headings(): array
    {
        return ['Employee no.', 'Name', 'Gross sheet 1', 'Gross sheet 2', 'Gross', 'Total deductions', 'Net pay', 'Carried forward out', 'EPF employer', 'ETF', 'Payment method'];
    }

    public function array(): array
    {
        return array_map(fn (array $row) => [
            $row['employee_no'],
            $row['name'],
            $row['gross_sheet1'],
            $row['gross_sheet2'],
            $row['gross'],
            $row['total_deductions'],
            $row['net_pay'],
            $row['carried_forward_out'],
            $row['epf_employer'],
            $row['etf'],
            $row['payment_method'],
        ], $this->report['rows']);
    }
}
