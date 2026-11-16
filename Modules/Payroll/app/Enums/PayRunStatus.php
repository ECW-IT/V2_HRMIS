<?php

namespace Modules\Payroll\Enums;

enum PayRunStatus: string
{
    case DRAFT = 'DRAFT';
    case CALCULATED = 'CALCULATED';
    case APPROVED = 'APPROVED';
    case LOCKED = 'LOCKED';
}
