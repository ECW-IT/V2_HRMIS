<?php

namespace Modules\Attendance\Support;

final class AttendanceNav
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public static function items(string $active): array
    {
        $screens = [
            'board',
            'exceptions',
            'ot',
            'late',
            'leave-requests',
            'leave-calendar',
            'period-close',
            'period-summary',
            'timecards',
        ];

        return [
            [
                'label' => 'Time & Leave',
                'active' => in_array($active, $screens, true),
                'children' => [
                    ['label' => 'Daily board', 'route' => route('attendance.board')],
                    ['label' => 'Exceptions', 'route' => route('attendance.exceptions')],
                    ['label' => 'OT approval', 'route' => route('attendance.ot')],
                    ['label' => 'Late review', 'route' => route('attendance.late')],
                    ['label' => 'Leave requests', 'route' => route('leave.requests.index')],
                    ['label' => 'Leave calendar', 'route' => route('leave.calendar')],
                    ['label' => 'Period close', 'route' => route('attendance.period-close')],
                    ['label' => 'Period summary', 'route' => route('attendance.period-summary')],
                ],
            ],
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
                'label' => 'Unresolved punches',
                'route' => route('attendance.unresolved.index'),
                'active' => $active === 'unresolved',
            ],
            [
                'label' => 'Leave types',
                'route' => route('leave.types.index'),
                'active' => $active === 'leave-types',
            ],
            [
                'label' => 'Leave policies',
                'route' => route('leave.policies.index'),
                'active' => $active === 'leave-policies',
            ],
        ];
    }
}
