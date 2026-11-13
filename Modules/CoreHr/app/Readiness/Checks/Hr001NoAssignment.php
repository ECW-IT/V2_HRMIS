<?php

namespace Modules\CoreHr\Readiness\Checks;

use Carbon\CarbonImmutable;
use Modules\CoreHr\Readiness\Severity;
use Modules\Organisation\Models\Company;

final class Hr001NoAssignment extends AbstractCheck
{
    public function code(): string
    {
        return 'HR001';
    }

    public function title(): string
    {
        return 'No assignment';
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
                        ->from('assignments')
                        ->whereColumn('assignments.employment_id', 'employments.id')
                        ->whereDate('assignments.effective_from', '<=', $day)
                        ->where(function ($inner) use ($day) {
                            $inner->whereNull('assignments.effective_to')
                                ->orWhereDate('assignments.effective_to', '>=', $day);
                        });
                })
        );
    }
}
