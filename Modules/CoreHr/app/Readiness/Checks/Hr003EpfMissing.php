<?php

namespace Modules\CoreHr\Readiness\Checks;

use Carbon\CarbonImmutable;
use Modules\CoreHr\Readiness\Severity;
use Modules\Organisation\Models\Company;

final class Hr003EpfMissing extends AbstractCheck
{
    public function code(): string
    {
        return 'HR003';
    }

    public function title(): string
    {
        return 'EPF number missing';
    }

    public function severity(): Severity
    {
        return Severity::BLOCKER;
    }

    public function failing(Company $company, CarbonImmutable $asOf): array
    {
        return $this->ids(
            $this->population($company, $asOf)
                ->where('epf_liable', true)
                ->where(function ($q) {
                    $q->whereNull('epf_no')->orWhere('epf_no', '');
                })
        );
    }
}
