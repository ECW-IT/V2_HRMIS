<?php

namespace Modules\Payroll\Ytd;

final readonly class YtdFigures
{
    public function __construct(
        public string $gross,
        public string $taxablePay,
        public string $tax,
        public string $epfEmployee,
        public string $epfEmployer,
        public string $etf,
        public int $months,
    ) {}
}
