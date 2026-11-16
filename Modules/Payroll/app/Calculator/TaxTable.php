<?php

namespace Modules\Payroll\Calculator;

use Modules\Payroll\Support\Money;

final class TaxTable
{
    /**
     * @param  list<array{width: ?string, percent: string}>  $slabs
     */
    public static function taxOn(int $taxableCents, array $slabs): int
    {
        if ($taxableCents <= 0) {
            return 0;
        }

        $remaining = $taxableCents;
        // Accumulate portionCents * percentCents; divide by 10000 once at the end.
        $acc = 0;

        foreach ($slabs as $slab) {
            if ($remaining <= 0) {
                break;
            }

            $percentCents = Money::toCents($slab['percent']);
            if ($slab['width'] === null) {
                $portion = $remaining;
            } else {
                $width = Money::toCents($slab['width']);
                $portion = $remaining < $width ? $remaining : $width;
            }

            $acc += $portion * $percentCents;
            $remaining -= $portion;
        }

        return Money::ratio($acc, 1, 10000);
    }
}
