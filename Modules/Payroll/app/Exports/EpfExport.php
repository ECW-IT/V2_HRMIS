<?php

namespace Modules\Payroll\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

final class EpfExport implements FromArray, WithHeadings
{
    /** @param  array{rows: list<array<string, mixed>>}  $report */
    public function __construct(private readonly array $report) {}

    public function headings(): array
    {
        return ['EPF no.', 'NIC', 'Name', 'EPF base', 'Employee 8%', 'Employer 12%', 'Total'];
    }

    public function array(): array
    {
        return array_map(fn (array $row) => [
            $row['epf_no'],
            $row['nic'],
            $row['name'],
            $row['epf_base'],
            $row['employee'],
            $row['employer'],
            $row['total'],
        ], $this->report['rows']);
    }
}
