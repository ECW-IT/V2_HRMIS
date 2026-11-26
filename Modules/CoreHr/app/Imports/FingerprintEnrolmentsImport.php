<?php

namespace Modules\CoreHr\Imports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Modules\CoreHr\Exports\FingerprintEnrolmentTemplateExport;

final class FingerprintEnrolmentsImport implements WithMultipleSheets
{
    /** @var list<array{row: int, data: array<string, string>}> */
    public array $rows = [];

    public function sheets(): array
    {
        return [
            0 => new FingerprintEnrolmentSheetImport($this),
        ];
    }
}

final class FingerprintEnrolmentSheetImport implements ToCollection, WithHeadingRow
{
    public function __construct(private readonly FingerprintEnrolmentsImport $import) {}

    public function collection(Collection $collection): void
    {
        foreach ($collection as $index => $row) {
            $rowNumber = $index + 2;
            $data = [];
            foreach ($row->toArray() as $key => $value) {
                $data[(string) $key] = $this->cellToString($value);
            }

            if ($this->isEmpty($data) || $this->isExample($data)) {
                continue;
            }

            $this->import->rows[] = [
                'row' => $rowNumber,
                'data' => $data,
            ];
        }
    }

    /** @param  array<string, string>  $data */
    private function isEmpty(array $data): bool
    {
        return ($data['employee_no'] ?? '') === ''
            && ($data['device_group'] ?? '') === ''
            && ($data['fingerprint_id'] ?? '') === ''
            && ($data['valid_from'] ?? '') === '';
    }

    /** @param  array<string, string>  $data */
    private function isExample(array $data): bool
    {
        return strcasecmp($data['employee_no'] ?? '', FingerprintEnrolmentTemplateExport::EXAMPLE_EMPLOYEE_NO) === 0;
    }

    private function cellToString(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
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
