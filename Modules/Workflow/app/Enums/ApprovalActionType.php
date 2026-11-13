<?php

namespace Modules\Workflow\Enums;

enum ApprovalActionType: string
{
    case CREATE = 'CREATE';
    case UPDATE = 'UPDATE';
    case DELETE = 'DELETE';
}
