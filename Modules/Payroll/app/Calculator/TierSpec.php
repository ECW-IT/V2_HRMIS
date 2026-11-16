<?php

namespace Modules\Payroll\Calculator;

final readonly class TierSpec
{
    public function __construct(
        public string $baseAmount,
        public string $perDayRate,
    ) {}
}
