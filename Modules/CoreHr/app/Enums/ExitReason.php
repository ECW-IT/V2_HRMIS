<?php

namespace Modules\CoreHr\Enums;

enum ExitReason: string
{
    case RESIGNATION = 'RESIGNATION';
    case TERMINATION = 'TERMINATION';
    case RETIREMENT = 'RETIREMENT';
    case CONTRACT_END = 'CONTRACT_END';
    case SISTER_COMPANY_TRANSFER = 'SISTER_COMPANY_TRANSFER';
    case DECEASED = 'DECEASED';
    case ABANDONMENT = 'ABANDONMENT';
}
