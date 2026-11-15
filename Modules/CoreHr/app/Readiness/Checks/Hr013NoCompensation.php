<?php

namespace Modules\CoreHr\Readiness\Checks;

use Carbon\CarbonImmutable;
use Modules\CoreHr\Readiness\Severity;
use Modules\Organisation\Models\Company;

final class Hr013NoCompensation extends AbstractCheck
{
    public function code(): string
    {
        return 'HR013';
    }

    public function title(): string
    {
        return 'No compensation';
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
                        ->from('employee_compensations')
                        ->whereColumn('employee_compensations.employment_id', 'employments.id')
                        ->whereDate('employee_compensations.effective_from', '<=', $day)
                        ->where(function ($inner) use ($day) {
                            $inner->whereNull('employee_compensations.effective_to')
                                ->orWhereDate('employee_compensations.effective_to', '>=', $day);
                        });
                })
        );
    }
}
