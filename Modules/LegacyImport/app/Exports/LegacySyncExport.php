<?php

namespace Modules\LegacyImport\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

final class LegacySyncExport implements WithMultipleSheets
{
    use Exportable;

    /**
     * @param  array{hires: Collection<int, array<string, mixed>>, exits: Collection<int, array<string, mixed>>, changes: Collection<int, array<string, mixed>>}  $rows
     */
    public function __construct(private readonly array $rows) {}

    public function sheets(): array
    {
        $hireHeadings = [
            'Company', 'Employee no.', 'Old employee no.', 'Name with initials', 'Calling name', 'NIC',
            'Hire date', 'Type', 'Department', 'Section', 'Job', 'Grade', 'EPF no.',
            'Bank', 'Branch', 'Account no.', 'Fingerprint group', 'Fingerprint ID',
        ];
        $exitHeadings = [
            'Company', 'Employee no.', 'Legacy employee no.', 'Name with initials',
            'Last working day', 'Reason', 'Remarks',
        ];
        $changeHeadings = [
            'Company', 'Employee no.', 'Legacy employee no.', 'Name with initials',
            'Change', 'Effective date', 'From', 'To',
        ];

        return [
            new ArraySheet('New hires', $hireHeadings, $this->rowsToLists($this->rows['hires'], $hireHeadings)),
            new ArraySheet('Exits', $exitHeadings, $this->rowsToLists($this->rows['exits'], $exitHeadings)),
            new ArraySheet('Changes', $changeHeadings, $this->rowsToLists($this->rows['changes'], $changeHeadings)),
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @param  list<string>  $headings
     * @return list<list<mixed>>
     */
    private function rowsToLists(Collection $rows, array $headings): array
    {
        return $rows->map(function (array $row) use ($headings) {
            return array_map(fn (string $key) => $row[$key] ?? '', $headings);
        })->values()->all();
    }
}
