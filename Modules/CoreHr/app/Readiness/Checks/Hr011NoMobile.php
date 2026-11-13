<?php

namespace Modules\CoreHr\Readiness\Checks;

use Carbon\CarbonImmutable;
use Modules\CoreHr\Enums\ContactType;
use Modules\CoreHr\Readiness\Severity;
use Modules\Organisation\Models\Company;

final class Hr011NoMobile extends AbstractCheck
{
    public function code(): string
    {
        return 'HR011';
    }

    public function title(): string
    {
        return 'No mobile number';
    }

    public function severity(): Severity
    {
        return Severity::WARNING;
    }

    public function failing(Company $company, CarbonImmutable $asOf): array
    {
        return $this->ids(
            $this->population($company, $asOf)
                ->whereNotExists(function ($q) {
                    $q->selectRaw('1')
                        ->from('person_contacts')
                        ->whereColumn('person_contacts.person_id', 'employments.person_id')
                        ->where('person_contacts.type', ContactType::MOBILE->value);
                })
        );
    }
}
