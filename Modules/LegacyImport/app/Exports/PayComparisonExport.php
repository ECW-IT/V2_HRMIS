<?php

namespace Modules\LegacyImport\Exports;

use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

final class PayComparisonExport implements WithMultipleSheets
{
    use Exportable;

    /**
     * @param  array{
     *   compared: list<list<mixed>>,
     *   only_v2: list<list<mixed>>,
     *   only_legacy: list<list<mixed>>
     * }  $data
     */
    public function __construct(private readonly array $data) {}

    public function sheets(): array
    {
        $head = [
            'Emp no.',
            'v2 basic', 'legacy basic', 'Δ basic',
            'v2 gross', 'legacy gross', 'Δ gross',
            'v2 EPF_EE', 'legacy EPF_EE', 'Δ EPF_EE',
            'v2 deductions', 'legacy deductions', 'Δ deductions',
            'v2 net', 'legacy net', 'Δ net',
            'Match',
        ];

        return [
            new ArraySheet('Compared', $head, $this->data['compared']),
            new ArraySheet('Only in v2', ['Emp no.', 'v2 net'], $this->data['only_v2']),
            new ArraySheet('Only in legacy', ['Emp no.', 'legacy net'], $this->data['only_legacy']),
        ];
    }
}
