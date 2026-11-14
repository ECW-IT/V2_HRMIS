<?php

namespace Modules\Attendance\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

final class DailyBoardExport implements FromArray, WithHeadings
{
    /** @param  list<array<string, mixed>>  $rows */
    public function __construct(private readonly array $rows) {}

    /** @return list<string> */
    public function headings(): array
    {
        return ['Employee no.', 'Name', 'Org unit', 'Shift', 'Status', 'First in', 'Last out', 'Late minutes'];
    }

    /** @return list<list<string>> */
    public function array(): array
    {
        return array_map(fn (array $row) => [
            (string) $row['employee_no'],
            (string) $row['name'],
            (string) $row['org_unit'],
            (string) $row['shift'],
            (string) $row['status'],
            (string) $row['first_in'],
            (string) $row['last_out'],
            (string) $row['late'],
        ], $this->rows);
    }
}
