<?php

namespace Modules\Organisation\Support;

final class OrganisationNav
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public static function items(string $active): array
    {
        $items = [
            [
                'icon' => '📍',
                'label' => 'Locations',
                'route' => route('org.locations.index'),
                'active' => $active === 'locations',
            ],
            [
                'icon' => '🗂️',
                'label' => 'Department break down',
                'route' => route('org.units.index'),
                'active' => $active === 'units',
            ],
            [
                'icon' => '💼',
                'label' => 'Designations',
                'route' => route('org.designations.index'),
                'active' => $active === 'designations',
            ],
            [
                'icon' => '🏷️',
                'label' => 'Employee categories',
                'route' => route('org.categories.index'),
                'active' => $active === 'categories',
            ],
            [
                'icon' => '🏪',
                'label' => 'Office / shop categories',
                'route' => route('org.staff-work-categories.index'),
                'active' => $active === 'staff-work-categories',
            ],
        ];

        if (auth()->user()?->hasRole('super-admin')) {
            array_unshift($items, [
                'icon' => '🏢',
                'label' => 'Companies',
                'route' => route('org.companies.index'),
                'active' => $active === 'companies',
            ]);
        }

        return $items;
    }
}
