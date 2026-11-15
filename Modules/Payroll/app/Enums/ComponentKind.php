<?php

namespace Modules\Payroll\Enums;

enum ComponentKind: string
{
    case EARNING = 'EARNING';
    case DEDUCTION = 'DEDUCTION';
    case EMPLOYER = 'EMPLOYER';
    case INFORMATION = 'INFORMATION';
}
