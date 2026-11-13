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
                'label' => 'Locations',
                'route' => route('org.locations.index'),
                'active' => $active === 'locations',
            ],
            [
                'label' => 'Org units',
                'route' => route('org.units.index'),
                'active' => $active === 'units',
            ],
            [
                'label' => 'Jobs',
                'route' => route('org.jobs.index'),
                'active' => $active === 'jobs',
            ],
            [
                'label' => 'Grades',
                'route' => route('org.grades.index'),
                'active' => $active === 'grades',
            ],
        ];

        if (auth()->user()?->hasRole('super-admin')) {
            array_unshift($items, [
                'label' => 'Companies',
                'route' => route('org.companies.index'),
                'active' => $active === 'companies',
            ]);
        }

        return $items;
    }
}
