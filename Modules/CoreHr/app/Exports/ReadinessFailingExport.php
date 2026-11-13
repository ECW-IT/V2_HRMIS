<?php

namespace Modules\CoreHr\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

final class ReadinessFailingExport implements FromArray, WithHeadings
{
    /** @param  list<array{employee_no: string, name: string, department: string, job: string}>  $rows */
    public function __construct(private readonly array $rows) {}

    public function headings(): array
    {
        return ['Employee no.', 'Name', 'Department', 'Job'];
    }

    public function array(): array
    {
        return array_map(fn (array $row) => [
            $row['employee_no'],
            $row['name'],
            $row['department'],
            $row['job'],
        ], $this->rows);
    }
}
