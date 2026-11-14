<?php

namespace Modules\Calendar\Support;

final class CalendarNav
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public static function items(string $active): array
    {
        return [
            [
                'label' => 'Holidays',
                'route' => route('calendar.holidays.index'),
                'active' => $active === 'holidays',
            ],
        ];
    }
}
