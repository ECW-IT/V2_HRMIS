<?php

namespace Modules\CoreHr\Readiness\Checks;

use Carbon\CarbonImmutable;
use Modules\CoreHr\Readiness\Severity;
use Modules\Organisation\Models\Company;

final class Hr012NoTimeProfile extends AbstractCheck
{
    public function code(): string
    {
        return 'HR012';
    }

    public function title(): string
    {
        return 'No time profile';
    }

    public function severity(): Severity
    {
        return Severity::BLOCKER;
    }

    public function failing(Company $company, CarbonImmutable $asOf): array
    {
        $day = $asOf->toDateString();

        return $this->ids(
            $this->population($company, $asOf)
                ->whereNotExists(function ($q) use ($day) {
                    $q->selectRaw('1')
                        ->from('employment_time_profiles')
                        ->whereColumn('employment_time_profiles.employment_id', 'employments.id')
                        ->whereDate('employment_time_profiles.effective_from', '<=', $day)
                        ->where(function ($inner) use ($day) {
                            $inner->whereNull('employment_time_profiles.effective_to')
                                ->orWhereDate('employment_time_profiles.effective_to', '>=', $day);
                        });
                })
        );
    }
}
