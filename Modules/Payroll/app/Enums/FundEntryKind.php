<?php

namespace Modules\Payroll\Enums;

enum FundEntryKind: string
{
    case CONTRIBUTION = 'CONTRIBUTION';
    case OPENING = 'OPENING';
    case REFUND = 'REFUND';
    case ADJUSTMENT = 'ADJUSTMENT';
}
