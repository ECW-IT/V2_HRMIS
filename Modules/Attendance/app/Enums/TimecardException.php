<?php

namespace Modules\Attendance\Enums;

enum TimecardException: string
{
    case MISSING_PUNCH = 'MISSING_PUNCH';
    case SHORT_PRESENCE = 'SHORT_PRESENCE';
    case NO_PROFILE = 'NO_PROFILE';
}
