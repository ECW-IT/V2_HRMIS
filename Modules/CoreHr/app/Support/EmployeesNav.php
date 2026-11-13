<?php

namespace Modules\CoreHr\Support;

final class EmployeesNav
{
    /** @return array<int, array<string, mixed>> */
    public static function items(string $active): array
    {
        $items = [
            [
                'label' => 'Employees',
                'route' => route('employees.index'),
                'active' => $active === 'employees',
            ],
        ];

        if (auth()->user()?->can('employee.create')) {
            $items[] = [
                'label' => 'Hire employee',
                'route' => route('employees.create'),
                'active' => $active === 'hire',
            ];
        }

        $items[] = [
            'label' => 'Readiness',
            'route' => route('employees.readiness'),
            'active' => $active === 'readiness',
        ];

        return $items;
    }
}
