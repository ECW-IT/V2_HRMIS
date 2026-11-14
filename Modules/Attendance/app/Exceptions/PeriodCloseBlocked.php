<?php

namespace Modules\Attendance\Exceptions;

use App\Exceptions\Domain\DomainException;
use Modules\Attendance\Support\ChecklistItem;

final class PeriodCloseBlocked extends DomainException
{
    /** @param  list<ChecklistItem>  $items */
    public function __construct(public array $items)
    {
        parent::__construct('The period cannot be closed.');
    }
}
