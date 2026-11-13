<?php

namespace Modules\Workflow\Enums;

enum ApproverType: string
{
    case ROLE = 'ROLE';
    case USER = 'USER';
    case MANAGER = 'MANAGER';
}
