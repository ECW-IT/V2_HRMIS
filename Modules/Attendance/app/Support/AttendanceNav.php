<?php

namespace Modules\Attendance\Support;

final class AttendanceNav
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public static function items(string $active): array
    {
        return [
            [
                'label' => 'Shifts',
                'route' => route('attendance.shifts.index'),
                'active' => $active === 'shifts',
            ],
            [
                'label' => 'Bulk time profiles',
                'route' => route('attendance.time-profiles.bulk'),
                'active' => $active === 'time-profiles',
            ],
            [
                'label' => 'Devices',
                'route' => route('attendance.devices.index'),
                'active' => $active === 'devices',
            ],
            [
                'label' => 'Unresolved',
                'route' => route('attendance.unresolved.index'),
                'active' => $active === 'unresolved',
            ],
        ];
    }
}
