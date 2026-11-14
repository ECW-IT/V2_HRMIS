<?php

namespace Modules\Attendance\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

final class PeriodSummaryExport implements FromArray, WithHeadings
{
    /** @param  list<list<string>>  $rows */
    public function __construct(private readonly array $rows) {}

    /** @return list<string> */
    public function headings(): array
    {
        return PeriodSummaryColumns::headings();
    }

    /** @return list<list<string>> */
    public function array(): array
    {
        return $this->rows;
    }
}
