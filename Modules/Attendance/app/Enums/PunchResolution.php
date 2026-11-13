<?php

namespace Modules\Attendance\Enums;

enum PunchResolution: string
{
    case RESOLVED = 'RESOLVED';
    case UNRESOLVED = 'UNRESOLVED';
}
