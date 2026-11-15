<?php

namespace Modules\Payroll\Enums;

enum CalcMethod: string
{
    case EARNED_BASIC = 'EARNED_BASIC';
    case OT = 'OT';
    case EXTRA_DAY = 'EXTRA_DAY';
    case ATTENDANCE_TIER = 'ATTENDANCE_TIER';
    case LATE = 'LATE';
    case EPF_EMPLOYEE = 'EPF_EMPLOYEE';
    case EPF_EMPLOYER = 'EPF_EMPLOYER';
    case ETF = 'ETF';
    case TAX = 'TAX';
    case CARRIED_FORWARD = 'CARRIED_FORWARD';
    case ADVANCE = 'ADVANCE';
    case LOAN = 'LOAN';
    case FIXED = 'FIXED';
    case PROPORTIONAL = 'PROPORTIONAL';
    case QUANTITY_RATE = 'QUANTITY_RATE';
    case NIGHT_UNITS = 'NIGHT_UNITS';
    case ENTRY = 'ENTRY';
}
