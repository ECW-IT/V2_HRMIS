<?php

namespace Modules\Payroll\Imports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

final class PayEntriesImport implements ToCollection, WithHeadingRow
{
    /** @var list<array{row: int, employee_no: string, component_code: string, quantity: ?string, amount: ?string, reference: ?string, note: ?string}> */
    public array $rows = [];

    public function collection(Collection $collection): void
    {
        foreach ($collection as $index => $row) {
            $rowNumber = $index + 1; // 1-based data row (heading excluded)

            $this->rows[] = [
                'row' => $rowNumber,
                'employee_no' => trim((string) ($row['employee_no'] ?? '')),
                'component_code' => strtoupper(trim((string) ($row['component_code'] ?? ''))),
                'quantity' => $this->nullableCell($row['quantity'] ?? null),
                'amount' => $this->nullableCell($row['amount'] ?? null),
                'reference' => $this->nullableCell($row['reference'] ?? null),
                'note' => $this->nullableCell($row['note'] ?? null),
            ];
        }
    }

    private function nullableCell(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }
}
