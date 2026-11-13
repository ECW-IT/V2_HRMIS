<?php

namespace Modules\CoreHr\Readiness\Checks;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Modules\CoreHr\Enums\EmploymentStatus;
use Modules\CoreHr\Models\Employment;
use Modules\CoreHr\Readiness\ReadinessCheck;
use Modules\Organisation\Models\Company;

abstract class AbstractCheck implements ReadinessCheck
{
    protected function population(Company $company, CarbonImmutable $asOf): Builder
    {
        return Employment::query()
            ->where('company_id', $company->id)
            ->whereIn('status', [EmploymentStatus::ACTIVE, EmploymentStatus::SUSPENDED])
            ->whereDate('hire_date', '<=', $asOf->toDateString());
    }

    /** @return list<int> */
    protected function ids(Builder $query): array
    {
        return $query->orderBy('employments.id')
            ->pluck('employments.id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }
}
