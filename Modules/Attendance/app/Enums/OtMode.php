<?php

namespace Modules\Attendance\Enums;

enum OtMode: string
{
    case NONE = 'NONE';
    case MORNING = 'MORNING';
    case EVENING = 'EVENING';
    case BOTH = 'BOTH';
}
