<?php

namespace Modules\Payroll\Rules;

final readonly class PayRules
{
    /**
     * @param  list<array{width: ?string, percent: string}>  $taxSlabs
     */
    public function __construct(
        public string $epfEmployeePercent,
        public string $epfEmployerPercent,
        public string $etfPercent,
        public array $taxSlabs,
        public int $lateDivisor,
        public string $attendanceMinOffset,
        public string $attendanceMaxOffset,
        public int $hoursPerMonth,
        public int $hoursPerDay,
        public string $otMultiplier,
    ) {}
}
