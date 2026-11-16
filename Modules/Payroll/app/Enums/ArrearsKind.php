<?php

namespace Modules\Payroll\Enums;

enum ArrearsKind: string
{
    case SHORTFALL = 'SHORTFALL';
    case RECOVERY = 'RECOVERY';
    case OPENING = 'OPENING';
    case ADJUSTMENT = 'ADJUSTMENT';
    case WRITE_OFF = 'WRITE_OFF';
}
