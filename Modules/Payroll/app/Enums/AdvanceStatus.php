<?php

namespace Modules\Payroll\Enums;

enum AdvanceStatus: string
{
    case PENDING = 'PENDING';
    case APPROVED = 'APPROVED';
    case PAID = 'PAID';
    case RECOVERED = 'RECOVERED';
    case REJECTED = 'REJECTED';
    case CANCELLED = 'CANCELLED';
    case CONVERTED = 'CONVERTED';
}
