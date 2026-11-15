<?php

namespace Modules\Payroll\Enums;

enum LoanStatus: string
{
    case PENDING = 'PENDING';
    case APPROVED = 'APPROVED';
    case ACTIVE = 'ACTIVE';
    case SETTLED = 'SETTLED';
    case REJECTED = 'REJECTED';
    case CANCELLED = 'CANCELLED';
}
