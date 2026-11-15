<?php

namespace Modules\Payroll\Enums;

enum InstalmentStatus: string
{
    case DUE = 'DUE';
    case RECOVERED = 'RECOVERED';
    case MOVED = 'MOVED';
    case WAIVED = 'WAIVED';
}
