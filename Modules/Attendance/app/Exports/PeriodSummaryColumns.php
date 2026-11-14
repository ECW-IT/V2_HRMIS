<?php

namespace Modules\Attendance\Exports;

final class PeriodSummaryColumns
{
    /** @return list<string> */
    public static function headings(): array
    {
        return [
            'Employee no.',
            'Name',
            'Grade',
            'Mode',
            'Employed days',
            'Expected working days',
            'Worked days',
            'Full days',
            'Half days',
            'Short days',
            'Short leave',
            'Paid leave',
            'Unpaid leave',
            'Credited days',
            'Extra days',
            'No-pay days',
            'Late minutes',
            'Chargeable late minutes',
            'OT hours',
            'Double OT hours',
        ];
    }
}
