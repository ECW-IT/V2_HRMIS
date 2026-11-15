<?php

namespace Modules\Payroll\Enums;

enum FundCode: string
{
    case SAVINGS = 'SAVINGS';
    case DEPOSIT = 'DEPOSIT';
    case WELFARE = 'WELFARE';
}
