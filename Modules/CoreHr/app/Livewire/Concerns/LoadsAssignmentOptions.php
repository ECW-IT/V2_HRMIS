<?php

namespace Modules\CoreHr\Livewire\Concerns;

use Modules\CoreHr\Support\OrgUnitOptions;
use Modules\Organisation\Models\Designation;
use Modules\Organisation\Models\Location;

trait LoadsAssignmentOptions
{
    /** @return array{orgUnits: list<array{id: int, label: string, depth: int, type: string}>, designations: mixed, locations: mixed} */
    protected function assignmentOptions(?int $companyId): array
    {
        return [
            'orgUnits' => $companyId === null ? [] : OrgUnitOptions::forCompany($companyId),
            'designations' => Designation::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'locations' => Location::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ];
    }
}
