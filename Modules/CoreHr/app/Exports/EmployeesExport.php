<?php

namespace Modules\CoreHr\Exports;

use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Modules\CoreHr\Models\Employment;

/**
 * Employee list export. Reads the filtered list query in chunks.
 * Bank details are never part of this export.
 */
final class EmployeesExport implements FromQuery, WithHeadings, WithMapping
{
    /** @param  Builder<Employment>  $query  the list query built by EmployeesIndex::query() */
    public function __construct(private readonly Builder $query) {}

    /** @return list<string> */
    public function headings(): array
    {
        return [
            'Employee no.',
            'Name with initials',
            'NIC',
            'Department / section',
            'Job',
            'Grade',
            'Type',
            'Hire date',
            'Status',
            'EPF no.',
        ];
    }

    public function query(): Builder
    {
        return $this->query;
    }

    /** @return list<string|null> */
    public function map($row): array
    {
        $department = $row->parent_unit_name !== null
            ? $row->parent_unit_name.' / '.$row->department_name
            : $row->department_name;

        return [
            $row->employee_no,
            $row->name_with_initials,
            $row->person_nic,
            $department,
            $row->job_name,
            $row->grade_name,
            $row->type->value,
            $row->hire_date?->toDateString(),
            $row->status->value,
            $row->epf_no,
        ];
    }
}
