<?php

namespace Modules\Leave\Enums;

enum LedgerEntryKind: string
{
    case GRANT = 'GRANT';
    case TAKEN = 'TAKEN';
    case RESTORED = 'RESTORED';
    case ADJUSTMENT = 'ADJUSTMENT';
}
