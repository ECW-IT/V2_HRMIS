<?php

namespace Modules\Payroll\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

final class CarriedForwardExport implements FromArray, WithHeadings
{
    /** @param  array{rows: list<array<string, mixed>>}  $report */
    public function __construct(private readonly array $report) {}

    public function headings(): array
    {
        return ['Employee no.', 'Name', 'Brought in', 'Recovered', 'New shortfall', 'Balance after'];
    }

    public function array(): array
    {
        return array_map(fn (array $row) => [
            $row['employee_no'],
            $row['name'],
            $row['brought_in'],
            $row['recovered'],
            $row['new_shortfall'],
            $row['balance_after'],
        ], $this->report['rows']);
    }
}
