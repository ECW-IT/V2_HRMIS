<?php

namespace Modules\Payroll\Support;

final class PayrollNav
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public static function items(string $active): array
    {
        $items = [];

        if (auth()->user()?->can('payroll.view')) {
            $items[] = [
                'label' => 'Pay components',
                'route' => route('payroll.components'),
                'active' => $active === 'components',
            ];
        }

        return $items;
    }
}
