<?php

namespace Modules\Payroll\Calculator;

use Modules\Payroll\Support\Money;

final readonly class PayResult
{
    /**
     * @param  list<PayLine>  $lines
     */
    public function __construct(
        public array $lines,
        public string $grossSheet1,
        public string $grossSheet2,
        public string $gross,
        public string $totalDeductions,
        public string $netPay,
        public string $carriedForwardOut,
        public string $epfBase,
        public string $taxablePay,
        public string $employerTotal,
    ) {}

    public function line(string $code): ?PayLine
    {
        foreach ($this->lines as $line) {
            if ($line->code === $code) {
                return $line;
            }
        }

        return null;
    }

    public function amount(string $code): string
    {
        $sum = 0;
        foreach ($this->lines as $line) {
            if ($line->code === $code) {
                $sum += Money::toCents($line->amount);
            }
        }

        return Money::fromCents($sum);
    }
}
