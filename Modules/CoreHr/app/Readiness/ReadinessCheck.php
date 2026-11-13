<?php

namespace Modules\CoreHr\Readiness;

use Carbon\CarbonImmutable;
use Modules\Organisation\Models\Company;

interface ReadinessCheck
{
    public function code(): string;

    public function title(): string;

    public function severity(): Severity;

    /** Ids of ACTIVE or SUSPENDED employments of $company, hired on or before $asOf, that fail this check on $asOf. */
    public function failing(Company $company, CarbonImmutable $asOf): array;
}
