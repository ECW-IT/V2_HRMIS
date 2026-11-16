<?php

namespace Modules\Payroll\Exceptions;

use App\Exceptions\Domain\DomainException;
use Modules\Payroll\Run\PreRunCheck;

final class PayRunBlocked extends DomainException
{
    /** @param  list<PreRunCheck>  $checks */
    public function __construct(public array $checks)
    {
        parent::__construct('The pay run cannot be calculated.');
    }
}
