<?php

namespace Modules\Payroll\Support;

use Carbon\CarbonImmutable;

final class TaxYear
{
    /** The tax year runs 1 April to 31 March. Returns the start date of the tax year containing $date. */
    public static function startFor(\DateTimeInterface|string $date): string
    {
        $day = CarbonImmutable::parse($date)->startOfDay();

        if ($day->month >= 4) {
            return $day->year.'-04-01';
        }

        return ($day->year - 1).'-04-01';
    }

    public static function endFor(string $taxYearStart): string
    {
        return CarbonImmutable::parse($taxYearStart)
            ->addYear()
            ->subDay()
            ->toDateString();
    }

    public static function label(string $taxYearStart): string
    {
        $start = CarbonImmutable::parse($taxYearStart);

        return $start->year.'/'.($start->year + 1);
    }
}
