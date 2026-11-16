<?php

namespace Modules\Payroll\Calculator;

use Modules\Payroll\Enums\CalcMethod;
use Modules\Payroll\Enums\ComponentKind;

final readonly class ComponentSpec
{
    public function __construct(
        public string $code,
        public ComponentKind $kind,
        public CalcMethod $method,
        public int $sheet,
        public bool $epfLiable,
        public bool $taxable,
        public int $displayOrder,
        public ?int $nightBaseHours = null,
    ) {}
}
