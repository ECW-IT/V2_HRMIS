<?php

namespace Modules\CoreHr\Readiness\Checks;

use Carbon\CarbonImmutable;
use Modules\CoreHr\Enums\EmploymentType;
use Modules\CoreHr\Readiness\Severity;
use Modules\Organisation\Models\Company;

final class Hr008ProbationOverdue extends AbstractCheck
{
    public function code(): string
    {
        return 'HR008';
    }

    public function title(): string
    {
        return 'Probation review overdue';
    }

    public function severity(): Severity
    {
        return Severity::WARNING;
    }

    public function failing(Company $company, CarbonImmutable $asOf): array
    {
        return $this->ids(
            $this->population($company, $asOf)
                ->where('type', EmploymentType::PROBATION->value)
                ->whereDate('confirmation_due_date', '<', $asOf->toDateString())
        );
    }
}
