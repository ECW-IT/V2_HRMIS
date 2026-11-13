<?php

namespace Modules\Workflow\Enums;

enum Decision: string
{
    case APPROVED = 'APPROVED';
    case REJECTED = 'REJECTED';
}
