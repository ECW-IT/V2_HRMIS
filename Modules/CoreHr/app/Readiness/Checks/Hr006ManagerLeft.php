<?php

namespace Modules\CoreHr\Readiness\Checks;

use Carbon\CarbonImmutable;
use Modules\CoreHr\Enums\EmploymentStatus;
use Modules\CoreHr\Readiness\Severity;
use Modules\Organisation\Models\Company;

final class Hr006ManagerLeft extends AbstractCheck
{
    public function code(): string
    {
        return 'HR006';
    }

    public function title(): string
    {
        return 'Manager has left';
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
                        ->join('employments as managers', 'managers.id', '=', 'assignments.manager_employment_id')
                        ->whereColumn('assignments.employment_id', 'employments.id')
                        ->whereDate('assignments.effective_from', '<=', $day)
                        ->where(function ($inner) use ($day) {
                            $inner->whereNull('assignments.effective_to')
                                ->orWhereDate('assignments.effective_to', '>=', $day);
                        })
                        ->where('managers.status', EmploymentStatus::EXITED->value);
                })
        );
    }
}
