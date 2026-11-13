<?php

namespace Modules\Attendance\Enums;

enum RejectionReason: string
{
    case BAD_TIMESTAMP = 'BAD_TIMESTAMP';
    case BAD_USER_ID = 'BAD_USER_ID';
    case MALFORMED = 'MALFORMED';
}
