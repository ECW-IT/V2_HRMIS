<?php

namespace Modules\Payroll\Calculator;

use Modules\Payroll\Enums\ExtraDayMethod;
use Modules\Payroll\Rules\PayRules;

final readonly class PayInput
{
    /**
     * @param  array<string, ComponentSpec>  $components
     * @param  list<PayItem>  $items
     * @param  list<PayItem>  $advances
     * @param  list<PayItem>  $loanInstalments
     * @param  array<string, string>  $overrides
     */
    public function __construct(
        public string $basicSalary,
        public string $expectedDays,
        public string $creditedDays,
        public string $extraDays,
        public int $lateMinutesChargeable,
        public int $otMinutes,
        public bool $epfLiable,
        public string $otRate,
        public ?ExtraDayMethod $extraDayMethod,
        public ?TierSpec $tier,
        public PayRules $rules,
        public array $components,
        public array $items,
        public string $carriedForwardIn,
        public array $advances,
        public array $loanInstalments,
        public array $overrides = [],
    ) {}
}
