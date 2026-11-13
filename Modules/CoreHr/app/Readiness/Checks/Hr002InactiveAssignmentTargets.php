<?php

namespace Modules\CoreHr\Readiness\Checks;

use Carbon\CarbonImmutable;
use Modules\CoreHr\Readiness\Severity;
use Modules\Organisation\Models\Company;

final class Hr002InactiveAssignmentTargets extends AbstractCheck
{
    public function code(): string
    {
        return 'HR002';
    }

    public function title(): string
    {
        return 'Assignment points to an inactive record';
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
                ->whereExists(function ($q) use ($day) {
                    $q->selectRaw('1')
                        ->from('assignments')
                        ->join('org_units', 'org_units.id', '=', 'assignments.org_unit_id')
                        ->join('hr_jobs', 'hr_jobs.id', '=', 'assignments.job_id')
                        ->join('grades', 'grades.id', '=', 'assignments.grade_id')
                        ->join('locations', 'locations.id', '=', 'assignments.location_id')
                        ->whereColumn('assignments.employment_id', 'employments.id')
                        ->whereDate('assignments.effective_from', '<=', $day)
                        ->where(function ($inner) use ($day) {
                            $inner->whereNull('assignments.effective_to')
                                ->orWhereDate('assignments.effective_to', '>=', $day);
                        })
                        ->where(function ($inner) {
                            $inner->where('org_units.is_active', false)
                                ->orWhere('hr_jobs.is_active', false)
                                ->orWhere('grades.is_active', false)
                                ->orWhere('locations.is_active', false);
                        });
                })
        );
    }
}
