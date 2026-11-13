<?php

namespace Modules\CoreHr\Enums;

enum ContactType: string
{
    case MOBILE = 'MOBILE';
    case HOME = 'HOME';
    case EMAIL = 'EMAIL';
    case WORK_EMAIL = 'WORK_EMAIL';
}
