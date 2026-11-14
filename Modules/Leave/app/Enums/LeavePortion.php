<?php

namespace Modules\Leave\Enums;

enum LeavePortion: string
{
    case FULL = 'FULL';
    case MORNING = 'MORNING';
    case EVENING = 'EVENING';
}
