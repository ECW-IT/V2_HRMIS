<?php

namespace Modules\CoreHr\Support;

final class EmployeesNav
{
    /** @return array<int, array<string, mixed>> */
    public static function items(string $active): array
    {
        $items = [
            [
                'icon' => '👥',
                'label' => 'Employees',
                'route' => route('employees.index'),
                'active' => $active === 'employees',
            ],
        ];

        if (auth()->user()?->can('employee.create')) {
            $items[] = [
                'icon' => '➕',
                'label' => 'Hire employee',
                'route' => route('employees.create'),
                'active' => $active === 'hire',
            ];
        }

        $items[] = [
            'icon' => '📊',
            'label' => 'Reports',
            'route' => route('employees.reports'),
            'active' => $active === 'reports',
        ];

        $items[] = [
            'icon' => '✅',
            'label' => 'Readiness',
            'route' => route('employees.readiness'),
            'active' => $active === 'readiness',
        ];

        return $items;
    }
}
