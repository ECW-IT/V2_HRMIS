<?php

namespace Modules\Payroll\Imports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

final class EmployeeComponentsImport implements ToCollection, WithHeadingRow
{
    /** @var list<array{row: int, data: array<string, string>}> */
    public array $rows = [];

    public function collection(Collection $collection): void
    {
        foreach ($collection as $index => $row) {
            $rowNumber = $index + 2; // heading is row 1
            $data = [];
            foreach ($row->toArray() as $key => $value) {
                $data[(string) $key] = $this->cellToString($value);
            }

            if ($this->isEmpty($data)) {
                continue;
            }

            $this->rows[] = [
                'row' => $rowNumber,
                'data' => $data,
            ];
        }
    }

    /** @param  array<string, string>  $data */
    private function isEmpty(array $data): bool
    {
        foreach ($data as $value) {
            if (trim($value) !== '') {
                return false;
            }
        }

        return true;
    }

    private function cellToString(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_float($value) || is_int($value)) {
            if (fmod((float) $value, 1.0) === 0.0) {
                return (string) (int) $value;
            }

            return rtrim(rtrim(sprintf('%.8F', $value), '0'), '.');
        }

        return trim((string) $value);
    }
}
