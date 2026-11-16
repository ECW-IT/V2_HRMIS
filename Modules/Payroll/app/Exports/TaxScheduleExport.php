<?php

namespace Modules\Payroll\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

final class TaxScheduleExport implements FromArray, WithHeadings
{
    /** @param  array{rows: list<array<string, mixed>>}  $report */
    public function __construct(private readonly array $report) {}

    public function headings(): array
    {
        return ['NIC', 'Name', 'Taxable pay', 'Tax'];
    }

    public function array(): array
    {
        return array_map(fn (array $row) => [
            $row['nic'],
            $row['name'],
            $row['taxable_pay'],
            $row['tax'],
        ], $this->report['rows']);
    }
}
