<?php

namespace Modules\Payroll\Calculator;

use Modules\Payroll\Enums\ComponentKind;

final readonly class PayLine
{
    public function __construct(
        public string $code,
        public ComponentKind $kind,
        public int $sheet,
        public ?string $quantity,
        public ?string $rate,
        public string $amount,
        public ?string $sourceType,
        public ?int $sourceId,
        public bool $isOverride,
        public ?string $note,
        public int $displayOrder,
    ) {}
}
