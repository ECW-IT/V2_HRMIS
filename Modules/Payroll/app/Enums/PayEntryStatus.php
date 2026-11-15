<?php

namespace Modules\Payroll\Enums;

enum PayEntryStatus: string
{
    case ACTIVE = 'ACTIVE';
    case CANCELLED = 'CANCELLED';
}
