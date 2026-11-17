<?php

namespace Modules\Payroll\Support;

final class PayrollNav
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public static function items(string $active): array
    {
        $user = auth()->user();
        $items = [];

        if ($user?->can('payroll.view')) {
            $items[] = self::item('Pay components', 'payroll.components', 'components', $active);
            $items[] = self::item('Loan types', 'payroll.loan-types', 'loan-types', $active);
            $items[] = self::item('Statutory rates', 'payroll.statutory-rates', 'statutory-rates', $active);
            $items[] = self::item('Tax slabs', 'payroll.tax-slabs', 'tax-slabs', $active);
            $items[] = self::item('Attendance tiers', 'payroll.attendance-tiers', 'attendance-tiers', $active);
            $items[] = self::item('Grade pay rules', 'payroll.grade-pay-rules', 'grade-pay-rules', $active);
            $items[] = self::item('Company pay settings', 'payroll.company-pay-settings', 'company-pay-settings', $active);
        }

        if ($user?->can('loan.manage')) {
            $items[] = self::item('Loans', 'payroll.loans', 'loans', $active);
        }

        if ($user?->can('advance.manage')) {
            $items[] = self::item('Salary advances', 'payroll.advances', 'advances', $active);
        }

        if ($user?->can('pay-entry.manage')) {
            $items[] = self::item('Pay entries', 'payroll.pay-entries', 'pay-entries', $active);
        }

        if ($user?->can('compensation.view')) {
            $items[] = self::item('Compensation', 'payroll.compensation', 'compensation', $active);
        }

        if ($user?->can('payroll.config')) {
            $items[] = self::item('Year-to-date openings', 'payroll.ytd-openings', 'ytd-openings', $active);
        }

        return $items;
    }

    /**
     * @return array<string, mixed>
     */
    private static function item(string $label, string $route, string $key, string $active): array
    {
        return [
            'label' => $label,
            'route' => route($route),
            'active' => $active === $key,
        ];
    }
}
