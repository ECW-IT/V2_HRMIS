<?php

namespace Modules\Calendar\Enums;

enum HolidayKind: string
{
    case POYA = 'POYA';
    case MERCANTILE = 'MERCANTILE';
    case PUBLIC = 'PUBLIC';
    case COMPANY = 'COMPANY';
}
