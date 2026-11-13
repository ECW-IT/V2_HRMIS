<?php

namespace Modules\CoreHr\Livewire\Concerns;

use Modules\CoreHr\Support\OrgUnitOptions;
use Modules\Organisation\Models\Grade;
use Modules\Organisation\Models\Job;
use Modules\Organisation\Models\Location;

trait LoadsAssignmentOptions
{
    /** @return array{orgUnits: list<array{id: int, label: string, depth: int}>, jobs: mixed, grades: mixed, locations: mixed} */
    protected function assignmentOptions(?int $companyId): array
    {
        return [
            'orgUnits' => $companyId === null ? [] : OrgUnitOptions::forCompany($companyId),
            'jobs' => Job::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'grades' => Grade::query()->where('is_active', true)->orderBy('rank')->get(['id', 'name']),
            'locations' => Location::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ];
    }
}
