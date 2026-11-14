<?php

namespace Modules\Calendar\Imports;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Modules\Calendar\Enums\HolidayKind;

final class HolidaysImport implements ToCollection, WithHeadingRow
{
    /** @var list<array{row: int, date: string, name: string, kind: string}> */
    public array $rows = [];

    /** @var list<array{row: int, message: string}> */
    public array $errors = [];

    public function collection(Collection $collection): void
    {
        $seenDates = [];

        foreach ($collection as $index => $row) {
            $rowNumber = $index + 1; // 1-based data row (heading excluded by WithHeadingRow)
            $dateRaw = trim((string) ($row['date'] ?? ''));
            $name = trim((string) ($row['name'] ?? ''));
            $kindRaw = strtoupper(trim((string) ($row['kind'] ?? '')));

            $normalizedDate = $this->parseDate($dateRaw);
            if ($normalizedDate === null) {
                $this->errors[] = ['row' => $rowNumber, 'message' => "Row {$rowNumber}: invalid date."];
            } elseif (isset($seenDates[$normalizedDate])) {
                $this->errors[] = ['row' => $rowNumber, 'message' => "Row {$rowNumber}: duplicate date in file."];
            } else {
                $seenDates[$normalizedDate] = true;
            }

            $kind = HolidayKind::tryFrom($kindRaw);
            if ($kind === null) {
                $this->errors[] = ['row' => $rowNumber, 'message' => "Row {$rowNumber}: unknown kind."];
            }

            if ($name === '') {
                $this->errors[] = ['row' => $rowNumber, 'message' => "Row {$rowNumber}: name is required."];
            }

            $this->rows[] = [
                'row' => $rowNumber,
                'date' => $normalizedDate ?? $dateRaw,
                'name' => $name,
                'kind' => $kindRaw,
            ];
        }
    }

    private function parseDate(string $value): ?string
    {
        if ($value === '') {
            return null;
        }

        try {
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1) {
                return Carbon::createFromFormat('Y-m-d', $value)->format('Y-m-d');
            }

            // Reject ambiguous day/month formats like 14/01/2027 for the preview error path.
            return null;
        } catch (\Throwable) {
            return null;
        }
    }
}
