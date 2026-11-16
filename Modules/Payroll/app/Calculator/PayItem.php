<?php

namespace Modules\Payroll\Calculator;

/** One amount, rate or quantity for a non-system component. */
final readonly class PayItem
{
    public function __construct(
        public string $code,
        public ?string $amount = null,
        public ?string $quantity = null,
        public ?string $sourceType = null,
        public ?int $sourceId = null,
    ) {}
}
