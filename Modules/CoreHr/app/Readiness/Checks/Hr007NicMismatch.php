<?php

namespace Modules\CoreHr\Readiness\Checks;

use Carbon\CarbonImmutable;
use Modules\CoreHr\Readiness\Severity;
use Modules\Organisation\Models\Company;

final class Hr007NicMismatch extends AbstractCheck
{
    public function code(): string
    {
        return 'HR007';
    }

    public function title(): string
    {
        return 'NIC does not match date of birth or gender';
    }

    public function severity(): Severity
    {
        return Severity::WARNING;
    }

    public function failing(Company $company, CarbonImmutable $asOf): array
    {
        return $this->ids(
            $this->population($company, $asOf)
                ->join('persons', 'persons.id', '=', 'employments.person_id')
                ->where('persons.nic_mismatch', true)
                ->select('employments.*')
        );
    }
}
