<?php

namespace Modules\Attendance\Enums;

enum AdjustmentStatus: string
{
    case PENDING = 'PENDING';
    case APPROVED = 'APPROVED';
    case REJECTED = 'REJECTED';
    case CANCELLED = 'CANCELLED';
}
