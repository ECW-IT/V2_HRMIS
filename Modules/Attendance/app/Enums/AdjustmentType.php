<?php

namespace Modules\Attendance\Enums;

enum AdjustmentType: string
{
    case ADD_PUNCH = 'ADD_PUNCH';
    case VOID_PUNCH = 'VOID_PUNCH';
}
