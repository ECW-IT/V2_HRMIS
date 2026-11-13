<?php

namespace Modules\CoreHr\Readiness\Checks;

use Carbon\CarbonImmutable;
use Modules\CoreHr\Readiness\Severity;
use Modules\Organisation\Models\Company;

final class Hr005NoEnrolment extends AbstractCheck
{
    public function code(): string
    {
        return 'HR005';
    }

    public function title(): string
    {
        return 'No fingerprint enrolment';
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
                        ->from('biometric_enrolments')
                        ->whereColumn('biometric_enrolments.employment_id', 'employments.id')
                        ->whereDate('biometric_enrolments.valid_from', '<=', $day)
                        ->where(function ($inner) use ($day) {
                            $inner->whereNull('biometric_enrolments.valid_to')
                                ->orWhereDate('biometric_enrolments.valid_to', '>=', $day);
                        });
                })
        );
    }
}
