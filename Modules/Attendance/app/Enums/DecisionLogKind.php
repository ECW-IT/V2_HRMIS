<?php

namespace Modules\Attendance\Enums;

enum DecisionLogKind: string
{
    case OT_APPROVED = 'OT_APPROVED';
    case OT_CLEARED = 'OT_CLEARED';
    case LATE_WAIVED = 'LATE_WAIVED';
    case LATE_RESTORED = 'LATE_RESTORED';
}
