<?php

namespace Modules\Attendance\Enums;

enum OverrideField: string
{
    case NOPAY_DAYS = 'NOPAY_DAYS';
    case LATE_MINUTES_CHARGEABLE = 'LATE_MINUTES_CHARGEABLE';
}
