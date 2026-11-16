<?php

namespace Modules\LegacyImport\Exports;

use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

final class PayReconciliationExport implements WithMultipleSheets
{
    use Exportable;

    /**
     * @param  array{
     *   issue_sheets: array<string, list<list<mixed>>>,
     *   loaded: list<list<mixed>>
     * }  $data
     */
    public function __construct(private readonly array $data) {}

    public function sheets(): array
    {
        $sheets = [];
        foreach ($this->data['issue_sheets'] as $code => $rows) {
            $sheets[] = new ArraySheet($code, ['Legacy profile', 'Emp no.', 'Message'], $rows);
        }
        $sheets[] = new ArraySheet('Loaded', [
            'Emp no.',
            'Basic',
            'Components count',
            'Components total',
            'Loans count',
            'Loans outstanding',
            'Advances count',
            'Advances amount',
        ], $this->data['loaded']);

        return $sheets;
    }
}
