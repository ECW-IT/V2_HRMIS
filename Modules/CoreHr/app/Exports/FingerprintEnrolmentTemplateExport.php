<?php

namespace Modules\CoreHr\Exports;

use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Modules\Attendance\Models\DeviceGroup;

final class FingerprintEnrolmentTemplateExport implements WithMultipleSheets
{
    use Exportable;

    public const EXAMPLE_EMPLOYEE_NO = 'SAMPLE';

    /** @return list<string> */
    public static function headings(): array
    {
        return [
            'employee_no',
            'device_group',
            'fingerprint_id',
            'valid_from',
        ];
    }

    public function sheets(): array
    {
        $groups = DeviceGroup::query()
            ->where('is_active', true)
            ->orderBy('code')
            ->get(['code', 'name']);

        $listRows = $groups
            ->map(static fn (DeviceGroup $group): array => [$group->code, $group->name])
            ->all();

        return [
            new HireTemplateSheet('enrolments', self::headings(), [[
                self::EXAMPLE_EMPLOYEE_NO,
                $groups->first()?->code ?? '',
                '1',
                '',
            ]]),
            new HireTemplateSheet('device_groups', ['code', 'name'], $listRows),
        ];
    }
}
