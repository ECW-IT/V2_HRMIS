<?php

namespace Modules\CoreHr\Enums;

enum EmploymentStatus: string
{
    case ACTIVE = 'ACTIVE';
    case SUSPENDED = 'SUSPENDED';
    case EXITED = 'EXITED';
}
