<?php

namespace Modules\Attendance\Enums;

enum DayType: string
{
    case FULL = 'FULL';
    case HALF = 'HALF';
    case SHORT = 'SHORT';
    case NONE = 'NONE';
}
