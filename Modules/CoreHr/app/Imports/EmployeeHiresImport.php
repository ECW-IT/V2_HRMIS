<?php

namespace Modules\CoreHr\Imports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Modules\CoreHr\Exports\EmployeeHireTemplateExport;

final class EmployeeHiresImport implements ToCollection, WithHeadingRow
{
    /** @var list<array{row: int, data: array<string, string>}> */
    public array $rows = [];

    /** @var list<string> */
    public array $errors = [];

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

            if ($this->isExample($data)) {
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
        $nic = $data['nic'] ?? '';
        $passport = $data['passport_no'] ?? '';
        $first = $data['first_name'] ?? '';
        $last = $data['last_name'] ?? '';

        return $nic === '' && $passport === '' && $first === '' && $last === '';
    }

    /** @param  array<string, string>  $data */
    private function isExample(array $data): bool
    {
        $nic = $data['nic'] ?? '';
        $first = $data['first_name'] ?? '';

        return strcasecmp($nic, EmployeeHireTemplateExport::EXAMPLE_NIC) === 0
            || strcasecmp($first, 'Sample') === 0;
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
