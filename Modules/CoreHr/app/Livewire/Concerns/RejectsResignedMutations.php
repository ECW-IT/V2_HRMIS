<?php

namespace Modules\CoreHr\Livewire\Concerns;

/**
 * For components that hold `public Employment $employment` and must not
 * mutate resigned (hrmis_inactive) employees, except payroll flags.
 */
trait RejectsResignedMutations
{
    protected function ensureNotResigned(): void
    {
        abort_if($this->employment->isResigned(), 403);
    }
}
