<?php

namespace Modules\CoreHr\Livewire;

class ResignedEmployeesIndex extends EmployeesIndex
{
    /** ACTIVE, SUSPENDED, EXITED or ALL. */
    public string $status = 'ALL';

    protected function hrmisActiveFilter(): bool
    {
        return false;
    }

    protected function pageTitle(): string
    {
        return 'Resigned employees';
    }

    protected function navKey(): string
    {
        return 'resigned';
    }

    protected function emptyMessage(): string
    {
        return 'No resigned employees match these filters.';
    }

    protected function showHireButton(): bool
    {
        return false;
    }

    protected function showFingerprintActions(): bool
    {
        return false;
    }
}
