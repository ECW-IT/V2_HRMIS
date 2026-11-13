<?php

namespace Modules\CoreHr\Readiness\Checks;

use Carbon\CarbonImmutable;
use Modules\CoreHr\Readiness\Severity;
use Modules\Organisation\Models\Company;

final class Hr010DocumentExpiring extends AbstractCheck
{
    public function code(): string
    {
        return 'HR010';
    }

    public function title(): string
    {
        return 'Document expired or expiring';
    }

    public function severity(): Severity
    {
        return Severity::WARNING;
    }

    public function failing(Company $company, CarbonImmutable $asOf): array
    {
        $limit = $asOf->addDays(30)->toDateString();

        return $this->ids(
            $this->population($company, $asOf)
                ->whereExists(function ($q) use ($limit) {
                    $q->selectRaw('1')
                        ->from('employee_documents')
                        ->whereColumn('employee_documents.person_id', 'employments.person_id')
                        ->whereNull('employee_documents.archived_at')
                        ->whereNotNull('employee_documents.expires_on')
                        ->whereDate('employee_documents.expires_on', '<=', $limit);
                })
        );
    }
}
