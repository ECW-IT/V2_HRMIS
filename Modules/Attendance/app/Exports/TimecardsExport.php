<?php

namespace Modules\Attendance\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

final class TimecardsExport implements FromArray, WithHeadings
{
    /** @param  list<array<string, mixed>>  $rows */
    public function __construct(private readonly array $rows) {}

    /** @return list<string> */
    public function headings(): array
    {
        return ['Date', 'First in', 'Last out', 'Day', 'Day value', 'Late minutes', 'OT minutes'];
    }

    /** @return list<list<string>> */
    public function array(): array
    {
        return array_map(fn (array $row) => [
            (string) $row['date'],
            (string) $row['first_in'],
            (string) $row['last_out'],
            (string) $row['day'],
            (string) $row['day_value'],
            (string) $row['late'],
            (string) $row['ot'],
        ], $this->rows);
    }
}
