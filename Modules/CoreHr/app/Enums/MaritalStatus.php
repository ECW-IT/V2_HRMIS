<?php

namespace Modules\CoreHr\Enums;

enum MaritalStatus: string
{
    case SINGLE = 'SINGLE';
    case MARRIED = 'MARRIED';
    case DIVORCED = 'DIVORCED';
    case WIDOWED = 'WIDOWED';
}
