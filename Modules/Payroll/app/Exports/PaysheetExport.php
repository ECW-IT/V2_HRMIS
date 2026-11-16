<?php

namespace Modules\Payroll\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

final class PaysheetExport implements FromArray, WithHeadings, WithTitle
{
    /** @param  array{title: string, columns: array<string, string>, rows: list<array<string, mixed>>, totals: array<string, string>}  $report */
    public function __construct(private readonly array $report, private readonly int $sheet) {}

    public function title(): string
    {
        return 'Paysheet '.$this->sheet;
    }

    public function headings(): array
    {
        $heads = ['Employee no.', 'Name', 'Job'];
        if ($this->sheet === 1) {
            $heads = array_merge($heads, ['Expected days', 'Credited days', 'OT hours']);
        }
        foreach ($this->report['columns'] as $code => $name) {
            $heads[] = $code;
        }

        return array_merge($heads, ['Gross', 'Deductions', 'Net']);
    }

    public function array(): array
    {
        $rows = [];
        foreach ($this->report['rows'] as $row) {
            $line = [$row['employee_no'], $row['name'], $row['job'] ?? ''];
            if ($this->sheet === 1) {
                $line[] = $row['expected_days'];
                $line[] = $row['credited_days'];
                $line[] = $row['ot_hours'];
            }
            foreach ($this->report['columns'] as $code => $_) {
                $line[] = $row[$code] ?? '0.00';
            }
            $line[] = $row['gross'];
            $line[] = $row['deductions'];
            $line[] = $row['net'];
            $rows[] = $line;
        }

        return $rows;
    }
}
