<?php

namespace Modules\CoreHr\Enums;

enum EmploymentType: string
{
    case PERMANENT = 'PERMANENT';
    case PROBATION = 'PROBATION';
    case INTERN = 'INTERN';
    case CONTRACT = 'CONTRACT';
}
