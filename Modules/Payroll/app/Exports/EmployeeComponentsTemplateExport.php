<?php

namespace Modules\Payroll\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

final class EmployeeComponentsTemplateExport implements FromArray, WithHeadings
{
    /** @return list<string> */
    public static function columnHeadings(): array
    {
        return [
            'Emp no',
            'Accommodation Allowance',
            'Attendance Allowance',
            'Meals Deduction',
            'OT Rate',
            'Performance Allowance',
            'Travel Allowance',
            'Fuel_Allowance',
            'Hostel fees',
            'Incentive I',
            'Incentive II',
            'Mobile_Allowance',
            'Over time hours',
            'Sales bonus',
            'Special Allowance',
            'Temporary_Allowance',
            'Vehicle_Allowance',
        ];
    }

    public function headings(): array
    {
        return self::columnHeadings();
    }

    public function array(): array
    {
        return [];
    }
}
