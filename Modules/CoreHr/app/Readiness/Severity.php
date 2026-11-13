<?php

namespace Modules\CoreHr\Readiness;

enum Severity: string
{
    case BLOCKER = 'BLOCKER';
    case WARNING = 'WARNING';
}
