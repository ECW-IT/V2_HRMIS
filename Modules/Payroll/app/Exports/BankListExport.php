<?php

namespace Modules\Payroll\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

final class BankListExport implements FromArray, WithHeadings, WithTitle
{
    /** @param  list<array<string, mixed>>  $rows */
    public function __construct(private readonly array $rows, private readonly string $sheetTitle = 'Bank') {}

    public function title(): string
    {
        return $this->sheetTitle;
    }

    public function headings(): array
    {
        return ['Employee no.', 'Name', 'Bank code', 'Branch code', 'Account no.', 'Account name', 'Amount', 'Reference'];
    }

    public function array(): array
    {
        return array_map(fn (array $row) => [
            $row['employee_no'],
            $row['name'],
            $row['bank_code'],
            $row['branch_code'],
            $row['account_no'],
            $row['account_name'],
            $row['amount'],
            $row['reference'],
        ], $this->rows);
    }
}
