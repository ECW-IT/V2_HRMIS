<?php

namespace Modules\LegacyImport\Exports;

use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

final class ReconciliationExport implements WithMultipleSheets
{
    use Exportable;

    /**
     * @param  array{
     *   summary_rows: list<list<string|int>>,
     *   issues: list<list<string|int|null>>,
     *   load_failures: list<list<string|int|null>>,
     *   bank_accounts: list<list<string>>
     * }  $data
     */
    public function __construct(private readonly array $data) {}

    public function sheets(): array
    {
        return [
            new ArraySheet('Summary', ['Metric', 'Company', 'Value'], $this->data['summary_rows']),
            new ArraySheet('Issues', ['Legacy HRM ID', 'Code', 'Severity', 'Message'], $this->data['issues']),
            new ArraySheet('Load failures', ['Legacy HRM ID', 'Employee no.', 'Error'], $this->data['load_failures']),
            new ArraySheet('Bank accounts to complete', ['Employee no.', 'Name', 'Account no.'], $this->data['bank_accounts']),
        ];
    }
}
